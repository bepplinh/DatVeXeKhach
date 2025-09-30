<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\JwtCookieService;
use App\Services\TwilioVerifyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class OtpAuthController extends Controller
{
    public function __construct(
        private TwilioVerifyService $sms,
        private EmailOtpService $emailOtp,
        private JwtCookieService $cookie
    ) {}

    // POST /api/auth/otp/start  { via: 'phone'|'email', phone? , email? }
    public function start(Request $request)
    {
        $data = $request->validate([
            'via'     => ['required','in:phone,email'],
            'phone'   => ['required_if:via,phone','regex:/^\+[1-9]\d{6,14}$/'],
            'email'   => ['required_if:via,email','email:rfc'],
            'channel' => ['nullable','in:sms,call,whatsapp,email,auto,sna'],
        ]);
    
        // 1) Chuẩn hoá định danh & rate-limit TRƯỚC khi gửi
        $id  = $data['via'] === 'phone'
            ? $data['phone']
            : strtolower($data['email']);
    
        $key = 'otp-start:'.$data['via'].':'.$id.'|'.$request->ip();
    
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Bạn thao tác quá nhanh, thử lại sau.'], 429);
        }
        RateLimiter::hit($key, 60);
    
        // 2) Gửi OTP theo kênh
        if ($data['via'] === 'phone') {
            $channel = $data['channel'] ?? 'sms'; // Twilio 8.7.1: channel là string
            $this->sms->start($data['phone'], $channel, ['locale' => 'vi']); // options ở tham số #3
            return response()->json([
                'success'=>true, 'via'=>'phone', 'status'=>'pending', 'to'=>$data['phone']
            ]);
        }
    
        // via = email
        $email = strtolower($data['email']);
        $this->emailOtp->start($email);
        return response()->json([
            'success'=>true, 'via'=>'email', 'status'=>'pending', 'to'=>$email
        ]);
    }
    // POST /api/auth/otp/verify { via, phone/email, code, password? }
    public function verify(Request $request)
    {
        $data = $request->validate([
            'via'   => ['required','in:phone,email'],
            'phone' => ['required_if:via,phone','regex:/^\+[1-9]\d{6,14}$/'],
            'email' => ['required_if:via,email','email:rfc'],
            'code'  => ['required','digits_between:4,8'],
            'password' => ['required_if:via,phone','nullable','string','min:6'],
        ]);

        $approved = false;
        $identifier = [];

        if ($data['via'] === 'phone') {
            $approved = $this->sms->check($data['phone'], $data['code']);
            $identifier = ['phone' => $data['phone']];
        } else {
            $email = strtolower($data['email']);
            $approved = $this->emailOtp->check($email, $data['code']);
            $identifier = ['email' => $email];
        }

        if (!$approved) {
            return response()->json(['success'=>false,'message'=>'Mã OTP không hợp lệ hoặc đã hết hạn'], 422);
        }

        // Tạo/tìm user (role=customer). Không mass-assign *_verified_at để an toàn.
        $user = User::where($identifier)->first();
        if (!$user) {
            $seed = $data['via'] === 'phone' ? $data['phone'] : $identifier['email'];
            $username = $this->uniqueUsernameFrom($seed);
            
            // Sử dụng password từ request nếu là phone, random password cho email
            $password = ($data['via'] === 'phone' && !empty($data['password'])) 
                ? $data['password'] 
                : Str::random(16);

            $user = User::create(array_merge($identifier, [
                'username' => $username,
                'password' => Hash::make($password),
                'role'     => 'customer',
            ]));
        }

        // Đánh dấu verified
        $touched = false;
        if ($data['via'] === 'phone' && !$user->phone_verified_at) {
            $user->phone_verified_at = Carbon::now(); $touched = true;
        }
        if ($data['via'] === 'email' && !$user->email_verified_at) {
            $user->email_verified_at = Carbon::now(); $touched = true;
        }
        if ($touched) $user->save();

        // Cấp JWT + gắn cookie 30 ngày
        $token = auth('api')->login($user);
        if (!$token) {
            return response()->json(['success'=>false,'message'=>'Không thể tạo token'], 500);
        }
        $resp  = $this->respondWithToken($token);
        return $this->cookie->attach($resp, $token);
    }

   

    // POST /api/auth/refresh
    public function refresh(Request $request)
    {
        try {
            $tokenIn = $request->cookie('jwt') ?: JWTAuth::getToken();
            if (!$tokenIn) return response()->json(['message'=>'Token không tồn tại'], 400);

            $new = JWTAuth::setToken($tokenIn)->refresh();
            $resp = $this->respondWithToken($new);
            return $this->cookie->attach($resp, $new);
        } catch (JWTException $e) {
            return response()->json(['message'=>'Không thể refresh token (hết 30 ngày hoặc token không hợp lệ)'], 401);
        }
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        try {
            $tokenIn = $request->cookie('jwt') ?: JWTAuth::getToken();
            if ($tokenIn) JWTAuth::setToken($tokenIn)->invalidate(true);
            else auth('api')->logout();
        } catch (\Throwable $e) { /* ignore */ }

        $resp = response()->json(['message'=>'Đã đăng xuất']);
        return $this->cookie->clear($resp);
    }

    protected function respondWithToken(string $token)
    {
        $ttlMinutes = (int) config('jwt.ttl', 60);
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlMinutes * 60,
        ]);
    }

    private function uniqueUsernameFrom(string $seed): string
    {
        $base = preg_replace('/[^a-z0-9]+/i', '', strtolower($seed)) ?: 'user';
        $u = $base; $i = 0;
        while (User::where('username', $u)->exists()) {
            $i++; $u = $base . $i;
        }
        return $u;
    }
}
