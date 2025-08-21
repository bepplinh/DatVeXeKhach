<?php

namespace App\Repository;

use App\Models\UserProvider;

class UserProviderRepository
{
    public function __construct(
        private UserProvider $model
    ) {}

    public function findByProvider(string $provider, string $providerId)
    {
        return $this->model->with('user')
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();
    }

    public function updateOrCreate(array $conditions, array $values): UserProvider
    {
        return $this->model->updateOrCreate($conditions, $values);
    }

    public function findByUserId(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->where('user_id', $userId)->get();
    }

    public function delete(UserProvider $userProvider): bool
    {
        return $userProvider->delete();
    }

    public function create(array $data): UserProvider
    {
        return $this->model->create($data);
    }
}