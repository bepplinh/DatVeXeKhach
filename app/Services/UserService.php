<?php

namespace App\Services;

use App\Models\User;
use App\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getAllUsers(int $perPage = 10): LengthAwarePaginator
    {
        return $this->userRepository->getAllPaginated($perPage);
    }

    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    public function createUser(array $data): User
    {
        return $this->userRepository->create($data);
    }

    public function updateUser(int $id, array $data): ?User
    {
        $user = $this->userRepository->findById($id);
        
        if (!$user) {
            return null;
        }

        $updated = $this->userRepository->update($id, $data);
        
        if ($updated) {
            // Refresh user data từ database để có dữ liệu mới nhất
            $user->refresh();
            return $user;
        }
        
        return null;
    }

    public function deleteUser(int $id): bool
    {
        return $this->userRepository->delete($id);
    }

    public function searchUsers(string $keyword, int $perPage = 10): LengthAwarePaginator
    {
        return $this->userRepository->search($keyword, $perPage);
    }
}