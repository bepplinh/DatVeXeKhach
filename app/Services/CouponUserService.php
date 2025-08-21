<?php

namespace App\Services;

use App\Models\CouponUser;
use App\Repository\CouponUserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CouponUserService
{
    protected $couponUserRepository;

    public function __construct(CouponUserRepository $couponUserRepository)
    {
        $this->couponUserRepository = $couponUserRepository;
    }

    public function getAllCouponUsers(): Collection
    {
        return $this->couponUserRepository->all();
    }

    public function getPaginatedCouponUsers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->couponUserRepository->paginate($perPage);
    }

    public function getFilteredCouponUsers(array $filters): LengthAwarePaginator
    {
        return $this->couponUserRepository->getFiltered($filters);
    }

    public function getCouponUserById(int $id): ?CouponUser
    {
        return $this->couponUserRepository->findById($id);
    }

    public function createCouponUser(array $data): CouponUser
    {
        $this->validateCouponUserData($data);
        
        // Kiểm tra xem user đã có coupon này chưa
        $existingCouponUser = $this->couponUserRepository->findByUserAndCoupon(
            $data['user_id'], 
            $data['coupon_id']
        );
        
        if ($existingCouponUser) {
            throw ValidationException::withMessages([
                'coupon_id' => 'User đã có coupon này rồi.'
            ]);
        }

        return $this->couponUserRepository->create($data);
    }

    public function updateCouponUser(int $id, array $data): bool
    {
        $couponUser = $this->couponUserRepository->findById($id);
        
        if (!$couponUser) {
            throw new \Exception('CouponUser không tồn tại.');
        }

        $this->validateCouponUserData($data, $id);

        // Kiểm tra xem user đã có coupon này chưa (trừ coupon-user hiện tại)
        if (isset($data['user_id']) && isset($data['coupon_id'])) {
            $existingCouponUser = $this->couponUserRepository->findByUserAndCoupon(
                $data['user_id'], 
                $data['coupon_id']
            );
            if ($existingCouponUser && $existingCouponUser->id !== $id) {
                throw ValidationException::withMessages([
                    'coupon_id' => 'User đã có coupon này rồi.'
                ]);
            }
        }

        return $this->couponUserRepository->update($couponUser, $data);
    }

    public function deleteCouponUser(int $id): bool
    {
        $couponUser = $this->couponUserRepository->findById($id);
        
        if (!$couponUser) {
            throw new \Exception('CouponUser không tồn tại.');
        }

        return $this->couponUserRepository->delete($couponUser);
    }

    public function getCouponUsersByUserId(int $userId): Collection
    {
        return $this->couponUserRepository->getByUserId($userId);
    }

    public function getCouponUsersByCouponId(int $couponId): Collection
    {
        return $this->couponUserRepository->getByCouponId($couponId);
    }

    public function markCouponAsUsed(int $id): bool
    {
        $couponUser = $this->couponUserRepository->findById($id);
        
        if (!$couponUser) {
            throw new \Exception('CouponUser không tồn tại.');
        }

        if ($couponUser->is_used) {
            throw new \Exception('Coupon này đã được sử dụng rồi.');
        }

        $data = [
            'is_used' => true,
            'used_at' => now()
        ];

        return $this->couponUserRepository->update($couponUser, $data);
    }

    public function assignCouponToUser(int $couponId, int $userId): CouponUser
    {
        // Kiểm tra xem user đã có coupon này chưa
        $existingCouponUser = $this->couponUserRepository->findByUserAndCoupon($userId, $couponId);
        
        if ($existingCouponUser) {
            throw new \Exception('User đã có coupon này rồi.');
        }

        $data = [
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'is_used' => false,
            'used_at' => null
        ];

        return $this->couponUserRepository->create($data);
    }

    public function getUserAvailableCoupons(int $userId): Collection
    {
        return $this->couponUserRepository->getUserAvailableCoupons($userId);
    }

    public function getUserUsedCoupons(int $userId): Collection
    {
        return $this->couponUserRepository->getUserUsedCoupons($userId);
    }

    protected function validateCouponUserData(array $data, int $id = null): void
    {
        $rules = [
            'user_id' => 'required|integer|exists:users,id',
            'coupon_id' => 'required|integer|exists:coupons,id',
            'is_used' => 'nullable|boolean',
            'used_at' => 'nullable|date',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
