<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\JwtCookieService;

class AuthController extends Controller
{
    public function __construct(private JwtCookieService $cookie) {}

    // POST /api/admin/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:' . strtolower($data['username']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $sec = RateLimiter::availableIn($key);
            return response()->json(['message' => "Thử lại sau {$sec} giây."], 429);
        }

        if (! $token = auth('api')->attempt([
            'username' => $data['username'],
            'password' => $data['password'],
        ])) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Sai thông tin đăng nhập'], 401);
        }
        RateLimiter::clear($key);

        $user = auth('api')->user();
        if ($user->role !== 'admin') {
            auth('api')->logout();
            return response()->json(['message' => 'Không phải admin'], 403);
        }

        $resp = $this->respondWithToken($token);
        return $this->cookie->attach($resp, $token);
    }

    // POST /api/customer/login - Đăng nhập bằng phone + password
    public function customerLogin(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'password' => ['required', 'string'],
            'remember_me' => ['boolean'],
        ]);

        $key = 'customer-login:' . $data['phone'] . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $sec = RateLimiter::availableIn($key);
            return response()->json(['message' => "Thử lại sau {$sec} giây."], 429);
        }

        if (! $token = auth('api')->attempt([
            'phone' => $data['phone'],
            'password' => $data['password'],
        ])) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Sai số điện thoại hoặc mật khẩu'], 401);
        }
        RateLimiter::clear($key);

        $user = auth('api')->user();
        if ($user->role !== 'customer') {
            auth('api')->logout();
            return response()->json(['message' => 'Tài khoản không hợp lệ'], 403);
        }

        // Kiểm tra phone đã verified chưa
        if (!$user->phone_verified_at) {
            auth('api')->logout();
            return response()->json(['message' => 'Số điện thoại chưa được xác thực'], 403);
        }

        // Cấu hình TTL dựa trên remember_me
        $ttlMinutes = $data['remember_me'] ? 43200 : 60; // 30 ngày hoặc 1 giờ
        config(['jwt.ttl' => $ttlMinutes]);

        $resp = $this->respondWithToken($token);
        return $this->cookie->attach($resp, $token);
    }

     // GET /api/me (middleware auth:api)
     public function me()
     {
         return response()->json(auth('api')->user());
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
}
