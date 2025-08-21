<?php

namespace App\Repository;

use App\Models\User;
use App\Repository\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
    protected User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Lấy danh sách tất cả users
     */
    public function getAll(): Collection
    {
        return $this->model->orderBy('created_at', 'desc')->get();
    }

    /**
     * Lấy danh sách users có phân trang
     */
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Tìm user theo ID
     */
    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Tìm user theo username
     */
    public function findByUsername(string $username): ?User
    {
        return $this->model->where('username', $username)->first();
    }

    /**
     * Tìm user theo email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Tạo mới user
     */
    public function create(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->model->create($data);
    }

    /**
     * Cập nhật user
     */
    public function update(int $id, array $data): bool
    {
        $user = $this->findById($id);
        
        if (!$user) {
            return false;
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $user->update($data);
    }

    /**
     * Xóa user
     */
    public function delete(int $id): bool
    {
        $user = $this->findById($id);
        
        if (!$user) {
            return false;
        }

        return $user->delete();
    }

    /**
     * Lấy users theo role
     */
    public function getByRole(string $role): Collection
    {
        return $this->model->where('role', $role)->orderBy('created_at', 'desc')->get();
    }

    /**
     * Tìm kiếm users
     */
    public function search(string $keyword, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->where(function ($query) use ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")
                  ->orWhere('username', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('phone', 'like', "%{$keyword}%");
        })->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Kiểm tra username đã tồn tại
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $query = $this->model->where('username', $username);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Kiểm tra email đã tồn tại
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('email', $email);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Lấy số lượng users theo role
     */
    public function countByRole(string $role): int
    {
        return $this->model->where('role', $role)->count();
    }

    /**
     * Cập nhật avatar
     */
    public function updateAvatar(int $id, string $avatarPath): bool
    {
        $user = $this->findById($id);
        
        if (!$user) {
            return false;
        }

        return $user->update(['avatar' => $avatarPath]);
    }

    /**
     * Xóa mềm user
     */
    public function softDelete(int $id): bool
    {
        // Implement soft delete logic if using SoftDeletes trait
        return $this->delete($id);
    }

    /**
     * Khôi phục user đã xóa mềm
     */
    public function restore(int $id): bool
    {
        // Implement restore logic if using SoftDeletes trait
        return true;
    }
}