<?php

namespace App\Services;

use App\Models\Coupon;
use App\Repository\CouponRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CouponService
{
    protected $couponRepository;

    public function __construct(CouponRepository $couponRepository)
    {
        $this->couponRepository = $couponRepository;
    }

    public function getAllCoupons(): Collection
    {
        return $this->couponRepository->all();
    }

    public function getPaginatedCoupons(int $perPage = 15): LengthAwarePaginator
    {
        return $this->couponRepository->paginate($perPage);
    }

    public function getFilteredCoupons(array $filters): LengthAwarePaginator
    {
        return $this->couponRepository->getFiltered($filters);
    }

    public function getCouponById(int $id): ?Coupon
    {
        return $this->couponRepository->findById($id);
    }

    public function getCouponByCode(string $code): ?Coupon
    {
        return $this->couponRepository->findByCode($code);
    }

    public function createCoupon(array $data): Coupon
    {
        $this->validateCouponData($data);
        
        // Kiểm tra code đã tồn tại chưa
        if ($this->couponRepository->findByCode($data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Mã giảm giá đã tồn tại.'
            ]);
        }

        return $this->couponRepository->create($data);
    }

    public function updateCoupon(int $id, array $data): bool
    {
        $coupon = $this->couponRepository->findById($id);
        
        if (!$coupon) {
            throw new \Exception('Coupon không tồn tại.');
        }

        $this->validateCouponData($data, $id);

        // Kiểm tra code đã tồn tại chưa (trừ coupon hiện tại)
        if (isset($data['code'])) {
            $existingCoupon = $this->couponRepository->findByCode($data['code']);
            if ($existingCoupon && $existingCoupon->id !== $id) {
                throw ValidationException::withMessages([
                    'code' => 'Mã giảm giá đã tồn tại.'
                ]);
            }
        }

        return $this->couponRepository->update($coupon, $data);
    }

    public function deleteCoupon(int $id): bool
    {
        $coupon = $this->couponRepository->findById($id);
        
        if (!$coupon) {
            throw new \Exception('Coupon không tồn tại.');
        }

        return $this->couponRepository->delete($coupon);
    }

    public function getActiveCoupons(): Collection
    {
        return $this->couponRepository->getActiveCoupons();
    }

    public function validateCoupon(string $code, float $orderAmount = 0): array
    {
        $coupon = $this->couponRepository->findByCode($code);
        
        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Mã giảm giá không tồn tại.'
            ];
        }

        if (!$coupon->isValid()) {
            return [
                'valid' => false,
                'message' => 'Mã giảm giá không còn hiệu lực.'
            ];
        }

        if ($orderAmount > 0 && $orderAmount < $coupon->minimum_order_amount) {
            return [
                'valid' => false,
                'message' => "Đơn hàng tối thiểu phải là " . number_format($coupon->minimum_order_amount) . " VNĐ."
            ];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount_amount' => $coupon->calculateDiscount($orderAmount)
        ];
    }

    public function applyCoupon(string $code, float $orderAmount): array
    {
        $validation = $this->validateCoupon($code, $orderAmount);
        
        if (!$validation['valid']) {
            return $validation;
        }

        $coupon = $validation['coupon'];
        $discountAmount = $coupon->calculateDiscount($orderAmount);
        
        // Tăng số lần sử dụng
        $this->couponRepository->incrementUsage($coupon);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount_amount' => $discountAmount,
            'final_amount' => $orderAmount - $discountAmount
        ];
    }

    public function searchCoupons(string $keyword): Collection
    {
        return $this->couponRepository->search($keyword);
    }

    private function validateCouponData(array $data, int $id = null): void
    {
        $rules = [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:fixed,percentage',
            'discount_value' => 'required|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'max_usage' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after:valid_from',
            'is_active' => 'boolean'
        ];

        // Nếu là update, không validate unique cho code
        if ($id) {
            $rules['code'] = 'required|string|max:50';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Validate logic bổ sung
        if (isset($data['discount_type']) && isset($data['discount_value'])) {
            if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Phần trăm giảm giá không được vượt quá 100%.'
                ]);
            }
        }
    }
} 