<?php

namespace App\Repository;

use App\Models\CouponUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponUserRepository
{
    protected $model;

    public function __construct(CouponUser $couponUser)
    {
        $this->model = $couponUser;
    }

    public function all(): Collection
    {
        return $this->model->with(['coupon', 'user'])->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['coupon', 'user'])->paginate($perPage);
    }

    public function getFiltered(array $filters): LengthAwarePaginator
    {
        $query = $this->model->with(['coupon', 'user']);

        // Filter by user_id
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by coupon_id
        if (isset($filters['coupon_id'])) {
            $query->where('coupon_id', $filters['coupon_id']);
        }

        // Filter by is_used
        if (isset($filters['is_used'])) {
            $query->where('is_used', $filters['is_used']);
        }

        // Sort
        $sortField = $filters['sort'] ?? 'created_at';
        $sortOrder = $filters['order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id): ?CouponUser
    {
        return $this->model->with(['coupon', 'user'])->find($id);
    }

    public function findByUserAndCoupon(int $userId, int $couponId): ?CouponUser
    {
        return $this->model->where('user_id', $userId)
                          ->where('coupon_id', $couponId)
                          ->first();
    }

    public function create(array $data): CouponUser
    {
        return $this->model->create($data);
    }

    public function update(CouponUser $couponUser, array $data): bool
    {
        return $couponUser->update($data);
    }

    public function delete(CouponUser $couponUser): bool
    {
        return $couponUser->delete();
    }

    public function getByUserId(int $userId): Collection
    {
        return $this->model->with(['coupon'])
                          ->where('user_id', $userId)
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    public function getByCouponId(int $couponId): Collection
    {
        return $this->model->with(['user'])
                          ->where('coupon_id', $couponId)
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    public function getUserAvailableCoupons(int $userId): Collection
    {
        return $this->model->with(['coupon'])
                          ->where('user_id', $userId)
                          ->where('is_used', false)
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    public function getUserUsedCoupons(int $userId): Collection
    {
        return $this->model->with(['coupon'])
                          ->where('user_id', $userId)
                          ->where('is_used', true)
                          ->orderBy('used_at', 'desc')
                          ->get();
    }

    public function getCouponUsageCount(int $couponId): int
    {
        return $this->model->where('coupon_id', $couponId)->count();
    }

    public function getCouponUsedCount(int $couponId): int
    {
        return $this->model->where('coupon_id', $couponId)
                          ->where('is_used', true)
                          ->count();
    }

    public function getUserCouponCount(int $userId): int
    {
        return $this->model->where('user_id', $userId)->count();
    }

    public function getUserAvailableCouponCount(int $userId): int
    {
        return $this->model->where('user_id', $userId)
                          ->where('is_used', false)
                          ->count();
    }
}
