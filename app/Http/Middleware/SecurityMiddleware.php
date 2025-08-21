<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    protected SecurityService $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     * Chỉ áp dụng rate limiting cho các hành động liên quan đến coupon
     */
    public function handle(Request $request, Closure $next, string $action = 'general', int $maxAttempts = 10, int $decayMinutes = 60): Response
    {
        $key = $this->getRateLimitKey($request);
        
        // Chỉ kiểm tra rate limit cho các hành động liên quan đến coupon
        if (in_array($action, ['coupon_apply', 'coupon_validate', 'coupon_use', 'birthday_coupon_request'])) {
            if (!$this->securityService->checkRateLimit($key, $action, $maxAttempts, $decayMinutes)) {
                Log::warning("Rate limit exceeded for coupon action: {$action}", [
                    'key' => $key,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã sử dụng coupon quá nhiều lần. Vui lòng thử lại sau.',
                    'retry_after' => $decayMinutes * 60,
                ], 429);
            }
        }

        // Kiểm tra xem IP có bị block không (chỉ cho coupon)
        if (in_array($action, ['coupon_apply', 'coupon_validate', 'coupon_use', 'birthday_coupon_request'])) {
            if ($this->isIpBlocked($request->ip(), $action)) {
                Log::warning("Blocked IP attempting to use coupon: {$action}", [
                    'ip' => $request->ip(),
                    'action' => $action,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'IP của bạn đã bị chặn do lạm dụng coupon.',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Tạo key cho rate limiting
     */
    private function getRateLimitKey(Request $request): string
    {
        if ($request->user()) {
            return "user_{$request->user()->id}";
        }

        return "ip_{$request->ip()}";
    }

    /**
     * Kiểm tra xem IP có bị block không
     */
    private function isIpBlocked(string $ip, string $action): bool
    {
        return $this->securityService->isUserBlocked(0, "ip_{$ip}_{$action}");
    }
}
