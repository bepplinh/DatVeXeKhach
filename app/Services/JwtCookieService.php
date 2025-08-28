<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class JwtCookieService
{
    public function minutes(): int
    {
        return (int) config('jwt.refresh_ttl', 43200); // 30 ngày
    }

    public function attach(Response|JsonResponse $response, string $token): Response|JsonResponse
    {
        $secure = app()->environment('production'); // bật HTTPS ở prod
        return $response->cookie(
            'jwt',               // name
            $token,              // value
            $this->minutes(),    // minutes
            '/',                 // path
            null,                // domain
            $secure,             // secure
            true,                // httpOnly
            false,               // raw
            'lax'                // sameSite: 'lax' | 'strict' | 'none'
        );
    }

    public function clear(Response|JsonResponse $response): Response|JsonResponse
    {
        $secure = app()->environment('production');
        return $response->cookie('jwt', '', -1, '/', null, $secure, true, false, 'lax');
    }
}
