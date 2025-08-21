<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

// JWT
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string|max:255|unique:users,username',
            'email'    => 'required|email:rfc|unique:users,email',
            'password' => 'required|string|min:4|max:72',
        ]);

        $user = User::create([
            'username' => $data['username'],
            'email'    => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        // Tạo token trực tiếp từ user
        $token = auth('api')->login($user);

        if (!$token) {
            return response()->json(['message' => 'Đăng ký thành công nhưng không tạo token được'], 500);
        }

        return $this->respondWithToken($token);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email:rfc',
            'password' => 'required|string',
        ]);

        // (Tuỳ chọn) Chống brute-force
        $key = 'login:' . mb_strtolower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Thử lại sau {$seconds} giây."
            ], 429);
        }

        if (! $token = auth('api')->attempt($credentials)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Email hoặc mật khẩu không đúng'], 401);
        }

        RateLimiter::clear($key);
        return $this->respondWithToken($token);
    }

    /**
     * GET /api/me
     * Lấy thông tin user hiện tại (cần middleware auth:api).
     */
    public function me()
    {
        return response()->json(auth('api')->user());
    }

    /**
     * POST /api/logout
     * Vô hiệu hoá token hiện tại.
     */
    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Đã đăng xuất']);
    }

    /**
     * POST /api/refresh
     * Refresh token cũ để lấy token mới (gửi kèm Authorization: Bearer <token_cũ>).
     * Dùng Facade JWTAuth để IDE không gạch đỏ.
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::getToken(); // lấy từ header Authorization
            if (!$token) {
                return response()->json(['message' => 'Token không tồn tại'], 400);
            }

            $newToken = JWTAuth::refresh($token);

            return $this->respondWithToken($newToken);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Không thể refresh token'], 401);
        }
    }

    /**
     * Chuẩn hoá JSON trả về cho các endpoint sinh token.
     * Dùng TTL từ config để tránh IDE gạch đỏ factory().
     */
    protected function respondWithToken(string $token)
    {
        $ttlMinutes = (int) config('jwt.ttl', 60);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlMinutes * 60, // giây
        ]);
    }
}
