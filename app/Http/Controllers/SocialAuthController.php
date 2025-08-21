<?php

namespace App\Http\Controllers;

use App\Http\Requests\Social\SocialLoginRequest;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;

class SocialAuthController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuthService
    ) {}

    public function loginWithToken(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->socialAuthService->loginWithGoogleToken($request->access_token);

        return response()->json([
            'access_token' => $result['token'],
            'token_type'   => 'bearer',
            'expires_in'   => $result['expires_in'],
        ]);
    }
}
