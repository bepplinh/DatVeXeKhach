<?php

namespace App\Http\Controllers;

use App\Services\DraftCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DraftCheckoutController extends Controller
{
    public function __construct(private DraftCheckoutService $draftService) {}

    /**
     * Tạo draft checkout mới
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'seat_ids' => ['required', 'array', 'min:1'],
            'seat_ids.*' => ['integer', 'exists:seats,id'],
            'passenger_name' => ['required', 'string', 'max:255'],
            'passenger_phone' => ['required', 'string', 'max:20'],
            'passenger_email' => ['nullable', 'email', 'max:255'],
            'pickup_location_id' => ['required', 'integer', 'exists:locations,id'],
            'dropoff_location_id' => ['required', 'integer', 'exists:locations,id'],
            'pickup_address' => ['nullable', 'string'],
            'dropoff_address' => ['nullable', 'string'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'notes' => ['nullable', 'string'],
            'passenger_info' => ['nullable', 'array'],
            'session_id' => ['nullable', 'string'], // Cho guest users
        ]);

        // Thêm user_id nếu đã đăng nhập
        if ($request->user()) {
            $data['user_id'] = $request->user()->id;
        }

        try {
            $draft = $this->draftService->createDraft($data);
            
            return response()->json([
                'success' => true,
                'data' => $draft->load(['trip', 'pickupLocation', 'dropoffLocation']),
                'message' => 'Tạo draft checkout thành công'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thông tin draft checkout
     */
    public function show(string $checkoutToken): JsonResponse
    {
        $draft = $this->draftService->getDraftByToken($checkoutToken);
        
        if (!$draft) {
            return response()->json([
                'success' => false,
                'message' => 'Draft checkout không tồn tại hoặc đã hết hạn'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $draft->load(['trip', 'pickupLocation', 'dropoffLocation', 'coupon'])
        ]);
    }

    /**
     * Cập nhật draft checkout
     */
    public function update(Request $request, string $checkoutToken): JsonResponse
    {
        $data = $request->validate([
            'passenger_name' => ['sometimes', 'string', 'max:255'],
            'passenger_phone' => ['sometimes', 'string', 'max:20'],
            'passenger_email' => ['nullable', 'email', 'max:255'],
            'pickup_location_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'dropoff_location_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'pickup_address' => ['nullable', 'string'],
            'dropoff_address' => ['nullable', 'string'],
            'total_price' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'coupon_id' => ['nullable', 'integer', 'exists:coupons,id'],
            'notes' => ['nullable', 'string'],
            'passenger_info' => ['nullable', 'array'],
        ]);

        $success = $this->draftService->updateDraft($checkoutToken, $data);
        
        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật draft checkout'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật draft checkout thành công'
        ]);
    }

    /**
     * Gia hạn thời gian draft
     */
    public function extend(Request $request, string $checkoutToken): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:5', 'max:60']
        ]);

        $minutes = $data['minutes'] ?? 15;
        $success = $this->draftService->extendDraft($checkoutToken, $minutes);
        
        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể gia hạn draft checkout'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Gia hạn draft checkout thành công thêm {$minutes} phút"
        ]);
    }

    /**
     * Chuyển draft thành booking (thanh toán thành công)
     */
    public function complete(Request $request, string $checkoutToken): JsonResponse
    {
        $result = $this->draftService->convertToBooking($checkoutToken);
        
        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['booking'],
            'message' => $result['message']
        ], 201);
    }

    /**
     * Hủy draft checkout
     */
    public function cancel(string $checkoutToken): JsonResponse
    {
        $success = $this->draftService->cancelDraft($checkoutToken);
        
        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể hủy draft checkout'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Hủy draft checkout thành công'
        ]);
    }

    /**
     * Lấy danh sách draft của user
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Cần đăng nhập để xem draft checkout'
            ], 401);
        }

        $drafts = $this->draftService->getUserDrafts($request->user()->id);
        
        return response()->json([
            'success' => true,
            'data' => $drafts
        ]);
    }

    /**
     * Lấy thống kê draft (admin only)
     */
    public function stats(): JsonResponse
    {
        $stats = $this->draftService->getDraftStats();
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Dọn dẹp draft hết hạn (admin only)
     */
    public function cleanup(): JsonResponse
    {
        $cleanedCount = $this->draftService->cleanupExpiredDrafts();
        
        return response()->json([
            'success' => true,
            'message' => "Đã dọn dẹp {$cleanedCount} draft hết hạn"
        ]);
    }
}

