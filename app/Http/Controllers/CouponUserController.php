<?php

namespace App\Http\Controllers;

use App\Http\Requests\CouponUser\StoreCouponUserRequest;
use App\Http\Requests\CouponUser\UpdateCouponUserRequest;
use App\Models\CouponUser;
use App\Services\CouponUserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CouponUserController extends Controller
{
    protected $couponUserService;

    public function __construct(CouponUserService $couponUserService)
    {
        $this->couponUserService = $couponUserService;
    }

    /**
     * Display a listing of the resource.
     * GET /api/coupon-users?page=1&per_page=15&user_id=1&coupon_id=1&is_used=true&sort=created_at&order=desc
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'user_id' => 'nullable|integer|exists:users,id',
                'coupon_id' => 'nullable|integer|exists:coupons,id',
                'is_used' => 'nullable|boolean',
                'sort' => 'nullable|string|in:id,user_id,coupon_id,is_used,used_at,created_at,updated_at',
                'order' => 'nullable|string|in:asc,desc',
            ]);

            $filters = [
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15),
                'user_id' => $request->get('user_id'),
                'coupon_id' => $request->get('coupon_id'),
                'is_used' => $request->get('is_used'),
                'sort' => $request->get('sort', 'created_at'),
                'order' => $request->get('order', 'desc'),
            ];

            $couponUsers = $this->couponUserService->getFilteredCouponUsers($filters);

            if ($couponUsers->total() === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Chưa có dữ liệu coupon-user nào!'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $couponUsers,
                'message' => 'Lấy danh sách coupon-user thành công.'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/coupon-users
     */
    public function store(StoreCouponUserRequest $request): JsonResponse
    {
        try {
            $couponUser = $this->couponUserService->createCouponUser($request->validated());

            return response()->json([
                'success' => true,
                'data' => $couponUser,
                'message' => 'Tạo coupon-user thành công.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * GET /api/coupon-users/{id}
     */
    public function show(CouponUser $couponUser): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $couponUser,
                'message' => 'Lấy thông tin coupon-user thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * PUT /api/coupon-users/{id}
     */
    public function update(UpdateCouponUserRequest $request, CouponUser $couponUser): JsonResponse
    {
        try {
            $success = $this->couponUserService->updateCouponUser($couponUser->id, $request->validated());

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cập nhật coupon-user thất bại.'
                ], 400);
            }

            $updatedCouponUser = $this->couponUserService->getCouponUserById($couponUser->id);

            return response()->json([
                'success' => true,
                'data' => $updatedCouponUser,
                'message' => 'Cập nhật coupon-user thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/coupon-users/{id}
     */
    public function destroy(CouponUser $couponUser): JsonResponse
    {
        try {
            $success = $this->couponUserService->deleteCouponUser($couponUser->id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xóa coupon-user thất bại.'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Xóa coupon-user thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coupon users by user ID
     * GET /api/coupon-users/user/{userId}
     */
    public function getByUserId(string $userId): JsonResponse
    {
        try {
            $couponUsers = $this->couponUserService->getCouponUsersByUserId($userId);

            if ($couponUsers->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'User chưa sử dụng coupon nào.'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $couponUsers,
                'message' => 'Lấy danh sách coupon của user thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get coupon users by coupon ID
     * GET /api/coupon-users/coupon/{couponId}
     */
    public function getByCouponId(string $couponId): JsonResponse
    {
        try {
            $couponUsers = $this->couponUserService->getCouponUsersByCouponId($couponId);

            return response()->json([
                'success' => true,
                'data' => $couponUsers,
                'message' => 'Lấy danh sách user sử dụng coupon thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark coupon as used for a user
     * POST /api/coupon-users/{id}/use
     */
    public function markAsUsed(string $id): JsonResponse
    {
        try {
            $success = $this->couponUserService->markCouponAsUsed($id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đánh dấu coupon đã sử dụng thất bại.'
                ], 400);
            }

            $couponUser = $this->couponUserService->getCouponUserById($id);

            return response()->json([
                'success' => true,
                'data' => $couponUser,
                'message' => 'Đánh dấu coupon đã sử dụng thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
