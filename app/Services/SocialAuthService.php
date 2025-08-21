<?php

namespace App\Services;

use App\Models\User;
use App\Repository\UserRepository;
use App\Repository\UserProviderRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserProviderRepository $userProviderRepository
    ) {}

    public function loginWithGoogleToken(string $accessToken): array
    {
        // 1) Lấy profile từ Google
        $googleUser = Socialite::driver('google')->stateless()->userFromToken($accessToken);

        // 2) Tìm user thông qua provider link
        $user = $this->findOrCreateUser($googleUser);

        // 3) Cập nhật/tạo provider link
        $this->updateOrCreateProviderLink($googleUser, $user);

        // 4) Phát JWT token
        $token = auth('api')->login($user);

        return [
            'token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }

    private function findOrCreateUser($googleUser)
    {
        // Tìm link trong user_providers
        $link = $this->userProviderRepository->findByProvider('google', $googleUser->getId());

        if ($link && $link->user) {
            return $link->user;
        }

        // Thử ghép theo email (nếu có)
        $user = null;
        if ($googleUser->getEmail()) {
            $user = $this->userRepository->findByEmail($googleUser->getEmail());
        }

        // Nếu vẫn chưa có user → tạo mới
        if (!$user) {
            $user = $this->createUserFromGoogleProfile($googleUser);
        }

        return $user;
    }

    private function createUserFromGoogleProfile($googleUser)
    {
        return $this->userRepository->create([
            'username'          => $googleUser->getName() ?: ($googleUser->getNickname() ?: 'User '.Str::random(6)),
            'email'             => $googleUser->getEmail(), // có thể null
            'password'          => Hash::make(Str::random(32)),
            'role'              => 'customer',
            'avatar'            => $googleUser->getAvatar(),
            'email_verified_at' => now(),
        ]);
    }

    private function updateOrCreateProviderLink($googleUser, User $user): void
    {
        $this->userProviderRepository->updateOrCreate(
            ['provider' => 'google', 'provider_id' => $googleUser->getId()],
            [
                'user_id'          => $user->id,
                'access_token'     => $googleUser->token ?? null,
                'refresh_token'    => $googleUser->refreshToken ?? null,
                'token_expires_at' => isset($googleUser->expiresIn) 
                    ? now()->addSeconds($googleUser->expiresIn) 
                    : null,
            ]
        );
    }
}