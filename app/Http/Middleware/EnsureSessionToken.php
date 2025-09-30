<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie as CookieFacade;

class EnsureSessionToken
{
    public function handle($request, Closure $next)
    {
        // Lấy từ header hoặc cookie nếu có
        $token = $request->header('X-Session-Token') ?? $request->cookie('x_session_token');

        if (!$token) {
            $token = Str::random(32);

            // ✅ Cách an toàn: dùng helper cookie() (tham số là PHÚT, không dùng named params)
            CookieFacade::queue(
                cookie(
                    'x_session_token', // name
                    $token,            // value
                    60 * 24 * 30,      // minutes = 30 ngày
                    '/',               // path
                    null,              // domain
                    $request->isSecure(), // secure (true nếu https)
                    false,             // httpOnly (false nếu frontend cần đọc JS)
                    false,             // raw
                    'Lax'              // sameSite
                )
            );

            // Đưa vào header để server-side có thể lấy ngay trong request hiện tại
            $request->headers->set('X-Session-Token', $token);
        }

        return $next($request);
    }
}
