<?php

namespace routes;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\BusTypeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\SeatFlowController;
use App\Http\Controllers\CouponUserController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TripStationController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\Client\GeminiChatController;
use App\Http\Controllers\Client\TripSearchController;
use App\Http\Controllers\SeatLayoutTemplateController;
use App\Http\Controllers\ScheduleTemplateTripController;
use App\Http\Controllers\Client\ClientLocationController;
use App\Http\Controllers\Client\Checkout\CheckoutController;
use App\Http\Controllers\Client\Checkout\SeatLockController;
use App\Http\Controllers\TripGenerateFromTemplateController;
use App\Http\Controllers\Client\Payment\PayOSWebhookController;


Route::post('/login',    [AuthController::class, 'login']);
Route::post('/customer/login', [AuthController::class, 'customerLogin']);
Route::post('/auth/social/{provider}', [SocialAuthController::class, 'loginWithToken'])
    ->whereIn('provider', ['google', 'facebook']);

// OTP Authentication Routes
Route::prefix('auth/otp')->group(function () {
    Route::post('/start', [OtpAuthController::class, 'start']);    // Gửi OTP
    Route::post('/verify', [OtpAuthController::class, 'verify']);  // Xác thực OTP
    Route::post('/refresh', [OtpAuthController::class, 'refresh']); // Refresh token
    Route::post('/logout', [OtpAuthController::class, 'logout']);   // Logout
});

// Public webhook for PayOS (should not require auth)
Route::post('payos/webhook', [PayOSWebhookController::class, 'handle']);

Route::middleware(['auth:api', 'x-session-token'])->group(function () {
    Route::get('/me',       [AuthController::class, 'me']);
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::put('drafts/{draftId}/payment', [CheckoutController::class, 'updateDraftPayment']);
    Route::post('checkout/lock-seats', [SeatLockController::class, 'lock']);
    Route::post('checkout/unlock-seats', [SeatLockController::class, 'unlock']);

    Route::prefix('trips/{tripId}')->group(function () {
        Route::post('seats/select',   [SeatFlowController::class, 'select']);
        Route::post('seats/unselect', [SeatFlowController::class, 'unselect']);

        Route::post('seats/checkout', [SeatFlowController::class, 'checkout']);
        Route::post('seats/release',  [SeatFlowController::class, 'release']);

        // Có thể làm webhook public từ PSP (không bắt buộc auth:api nếu cần)
        Route::post('seats/payment/success', [SeatFlowController::class, 'paymentSuccess']);
    });
});

Route::apiResource('/users', UserController::class);
Route::apiResource('/buses', BusController::class);
Route::apiResource('/type_buses', BusTypeController::class);
Route::apiResource('/routes', RouteController::class);
Route::apiResource('/offices', OfficeController::class);
Route::apiResource('trips', TripController::class);
Route::apiResource('trip-stations', TripStationController::class);
Route::apiResource('seat-layout-templates', SeatLayoutTemplateController::class);
Route::apiResource('/coupons', CouponController::class);

Route::apiResource('locations', LocationController::class);

// Location hierarchy routes  
Route::get('locations-tree', [LocationController::class, 'tree']);
Route::get('/location/cities', [LocationController::class, 'cities']);
Route::get('districts', [LocationController::class, 'districts']);
Route::get('wards', [LocationController::class, 'wards']);

Route::get('coupons/active', [CouponController::class, 'active']);
Route::get('coupon-users/user/{userId}', [CouponUserController::class, 'getByUserId']);
Route::get('coupon-users/coupon/{couponId}', [CouponUserController::class, 'getByCouponId']);

Route::apiResource('schedule-template-trips', ScheduleTemplateTripController::class);
Route::post('trips/generate-from-templates', [TripGenerateFromTemplateController::class, 'generate']);

//---------Client API------------

Route::get('/client/locations', [ClientLocationController::class, 'index']);
Route::get('/client/locations/search', [ClientLocationController::class, 'search']);
Route::post('/client/trips/search', [TripSearchController::class, 'search']);

Route::post('/ai/chat', [GeminiChatController::class, 'chat']);
