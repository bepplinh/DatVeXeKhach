<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('draft_checkouts', function (Blueprint $table) {
            $table->id();
            
            // Thông tin chuyến đi và ghế
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->json('seat_ids'); // Mảng các ID ghế đã chọn
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Thông tin hành khách
            $table->string('passenger_name');
            $table->string('passenger_phone');
            $table->string('passenger_email')->nullable();
            
            // Thông tin địa điểm đón/trả
            $table->foreignId('pickup_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('dropoff_location_id')->constrained('locations')->cascadeOnDelete();
            $table->text('pickup_address')->nullable(); // Địa chỉ chi tiết đón
            $table->text('dropoff_address')->nullable(); // Địa chỉ chi tiết trả
            
            // Thông tin thanh toán
            $table->decimal('total_price', 10, 0)->default(0);
            $table->decimal('discount_amount', 10, 0)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            
            // Thông tin bổ sung
            $table->text('notes')->nullable(); // Ghi chú đặc biệt
            $table->json('passenger_info')->nullable(); // Thông tin bổ sung khác (CCCD, ngày sinh, etc.)
            
            // Trạng thái và thời gian
            $table->enum('status', ['draft', 'processing', 'expired', 'completed'])->default('draft');
            $table->timestamp('expires_at'); // Thời gian hết hạn draft (thường 15-30 phút)
            $table->timestamp('completed_at')->nullable(); // Khi chuyển thành booking
            
            $table->string('checkout_token')->unique(); // Token duy nhất cho checkout
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['trip_id', 'status']);
            $table->index(['checkout_token']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_checkouts');
    }
};
