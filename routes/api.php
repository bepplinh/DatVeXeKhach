<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BusTypeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\SelectingController;
use App\Http\Controllers\CouponUserController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TripStationController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\SeatLayoutTemplateController;
use App\Http\Controllers\ScheduleTemplateTripController;
use App\Http\Controllers\TripGenerateFromTemplateController;
use App\Http\Controllers\SeatSelectionController;


Route::post('/register', [AuthController::class, 'register']); 
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/auth/social/{provider}', [SocialAuthController::class, 'loginWithToken'])
    ->whereIn('provider', ['google','facebook']);

// OTP Authentication Routes
Route::prefix('auth/otp')->group(function () {
    Route::post('/start', [OtpAuthController::class, 'start']);    // Gửi OTP
    Route::post('/verify', [OtpAuthController::class, 'verify']);  // Xác thực OTP
    Route::post('/refresh', [OtpAuthController::class, 'refresh']); // Refresh token
    Route::post('/logout', [OtpAuthController::class, 'logout']);   // Logout
});

Route::middleware('auth:api')->group(function () {
    Route::get('/me',       [AuthController::class, 'me']);
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::apiResource('/users', UserController::class);
Route::apiResource('/buses', BusController::class);
Route::apiResource('/type_buses', BusTypeController::class);
Route::apiResource('/routes', RouteController::class);
Route::apiResource('/offices', OfficeController::class);
Route::apiResource('trips', TripController::class);
Route::apiResource('trip-stations', TripStationController::class);
Route::apiResource('seat-layout-templates', SeatLayoutTemplateController::class);

Route::apiResource('locations', LocationController::class);

// Location hierarchy routes  
Route::get('locations-tree', [LocationController::class, 'tree']);
Route::get('/location/cities', [LocationController::class, 'cities']);
Route::get('districts', [LocationController::class, 'districts']);
Route::get('wards', [LocationController::class, 'wards']);


// Coupon routes - RESTful API (có bảo mật)
Route::middleware('security:coupon_apply,5,60')->group(function () {
    Route::post('coupons/apply', [CouponController::class, 'apply']);
});

Route::middleware('security:coupon_validate,10,60')->group(function () {
    Route::post('coupons/validate', [CouponController::class, 'validate']);
});

Route::middleware('security:coupon_use,3,60')->group(function () {
    Route::post('coupon-users/{id}/use', [CouponUserController::class, 'markAsUsed']);
});

// CouponUser routes - RESTful API (có bảo mật)
Route::middleware('security:birthday_coupon_request,1,525600')->group(function () {
    Route::apiResource('coupon-users', CouponUserController::class);
});

// Các route khác KHÔNG có bảo mật
Route::get('coupons/active', [CouponController::class, 'active']);
Route::get('coupon-users/user/{userId}', [CouponUserController::class, 'getByUserId']);
Route::get('coupon-users/coupon/{couponId}', [CouponUserController::class, 'getByCouponId']);

Route::apiResource('schedule-template-trips', ScheduleTemplateTripController::class);
Route::post('trips/generate-from-templates', [TripGenerateFromTemplateController::class, 'generate']);

Route::prefix('trips/{tripId}')->group(function () {
    // “đang chọn” chỉ mang tính hiển thị
    Route::post('/selecting', [SelectingController::class, 'select']);
    Route::delete('/selecting/{seatId}', [SelectingController::class, 'unselect']);

    // Đặt ghế - ai book trước thắng
    Route::post('/bookings', [BookingController::class, 'store']);
    
    // Seat Selection API - chọn ghế trước khi đặt
    Route::prefix('seats')->group(function () {
        Route::post('/select', [SeatSelectionController::class, 'selectSeats']);
        Route::post('/unselect', [SeatSelectionController::class, 'unselectSeats']);
        Route::delete('/unselect-all', [SeatSelectionController::class, 'unselectAllSeats']);
        Route::get('/selections', [SeatSelectionController::class, 'getUserSelections']);
        Route::post('/check-status', [SeatSelectionController::class, 'checkSeatsStatus']);
    });
    
    // Booking API - đặt ghế sau khi đã chọn
    Route::prefix('bookings')->group(function () {
        Route::post('/', [BookingController::class, 'store']);
        Route::get('/selections', [BookingController::class, 'getUserSelections']);
        Route::delete('/selections', [BookingController::class, 'cancelSelections']);
    });
});



