<?php

namespace App\Http\Controllers;

use App\Http\Requests\CouponStoreRequest;
use App\Http\Requests\CouponUpdateRequest;
use App\Http\Requests\CouponValidateRequest;
use App\Http\Requests\CouponApplyRequest;
use App\Services\CouponService;
use App\Services\SecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    protected $couponService;
    protected $securityService;

    public function __construct(CouponService $couponService, SecurityService $securityService)
    {
        $this->couponService = $couponService;
        $this->securityService = $securityService;
    }

    /**
     * Display a listing of the resource.
     * GET /api/coupons?page=1&per_page=15&search=keyword&sort=created_at&order=desc&status=active
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
                'sort' => 'nullable|string|in:id,code,name,discount_type,discount_value,created_at,updated_at',
                'order' => 'nullable|string|in:asc,desc',
                'status' => 'nullable|string|in:active,inactive,all',
                'discount_type' => 'nullable|string|in:fixed,percentage',
            ]);

            $filters = [
                'page' => $request->get('page', 1),
                'per_page' => $request->get('per_page', 15),
                'search' => $request->get('search'),
                'sort' => $request->get('sort', 'created_at'),
                'order' => $request->get('order', 'desc'),
                'status' => $request->get('status', 'all'),
                'discount_type' => $request->get('discount_type'),
            ];

            $coupons = $this->couponService->getFilteredCoupons($filters);

            if ($coupons->total() === 0) {
                return response()->json([
                    'message' => 'Chưa có mã giảm giá nào!'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $coupons,
                'message' => 'Lấy danh sách mã giảm giá thành công.'
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
     * POST /api/coupons
     */
    public function store(CouponStoreRequest $request): JsonResponse
    {
        try {
            $coupon = $this->couponService->createCoupon($request->validated());

            return response()->json([
                'success' => true,
                'data' => $coupon,
                'message' => 'Tạo mã giảm giá thành công.'
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
     * GET /api/coupons/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $coupon = $this->couponService->getCouponById($id);

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mã giảm giá không tồn tại.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $coupon,
                'message' => 'Lấy thông tin mã giảm giá thành công.'
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
     * PUT /api/coupons/{id}
     */
    public function update(CouponUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $success = $this->couponService->updateCoupon($id, $request->validated());

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cập nhật mã giảm giá thất bại.'
                ], 400);
            }

            $coupon = $this->couponService->getCouponById($id);

            return response()->json([
                'success' => true,
                'data' => $coupon,
                'message' => 'Cập nhật mã giảm giá thành công.'
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
     * DELETE /api/coupons/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $success = $this->couponService->deleteCoupon($id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Xóa mã giảm giá thất bại.'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Xóa mã giảm giá thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active coupons
     * GET /api/coupons/active
     */
    public function active(): JsonResponse
    {
        try {
            $coupons = $this->couponService->getActiveCoupons();

            // Kiểm tra xem có dữ liệu hay không
            if ($coupons->count() === 0) {
                return response()->json([
                    'success' => true,
                    'data' => $coupons,
                    'message' => 'Chưa có mã giảm giá nào!'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $coupons,
                'message' => 'Lấy danh sách mã giảm giá đang hoạt động thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate coupon code
     * POST /api/coupons/validate
     */
    public function validate(CouponValidateRequest $request): JsonResponse
    {
        try {
            // Kiểm tra bảo mật trước khi validate coupon
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn cần đăng nhập để sử dụng coupon.'
                ], 401);
            }

            $userId = Auth::id();
            if (!$this->securityService->canValidateCoupon($userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã validate coupon quá nhiều lần. Vui lòng thử lại sau 1 giờ.'
                ], 429);
            }

            $validated = $request->validated();
            
            $result = $this->couponService->validateCoupon(
                $validated['code'],
                $validated['order_amount'] ?? 0
            );

            return response()->json([
                'success' => $result['valid'],
                'data' => $result,
                'message' => $result['valid'] ? 'Mã giảm giá hợp lệ.' : $result['message']
            ], $result['valid'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply coupon to order
     * POST /api/coupons/apply
     */
    public function apply(CouponApplyRequest $request): JsonResponse
    {
        try {
            // Kiểm tra bảo mật trước khi áp dụng coupon
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn cần đăng nhập để sử dụng coupon.'
                ], 401);
            }

            $userId = Auth::id();
            if (!$this->securityService->canApplyCoupon($userId, $request->code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã áp dụng coupon quá nhiều lần. Vui lòng thử lại sau 1 giờ.'
                ], 429);
            }

            $validated = $request->validated();
            
            $result = $this->couponService->applyCoupon(
                $validated['code'],
                $validated['order_amount']
            );

            return response()->json([
                'success' => $result['valid'],
                'data' => $result,
                'message' => $result['valid'] ? 'Áp dụng mã giảm giá thành công.' : $result['message']
            ], $result['valid'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
