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
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        
            $table->string('session_token', 64)->index();
        
            $table->string('payment_provider', 20)->nullable();
            // Gợi ý: unique theo (provider, intent)
            $table->string('payment_intent_id', 100)->nullable();
            $table->unique(['payment_provider', 'payment_intent_id']);
        
            // Thông tin hành khách (cho phép nullable để PATCH sau)
            $table->string('passenger_name')->nullable();
            $table->string('passenger_phone')->nullable();
            $table->string('passenger_email')->nullable();
        
            // Điểm đón/trả (đổi sang nullOnDelete để giữ draft nếu location bị xóa/sửa)
            $table->foreignId('pickup_location_id')->nullable()
                  ->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()
                  ->constrained('locations')->nullOnDelete();
        
            // Snapshot tên/địa chỉ điểm đón/trả tại thời điểm đặt (tránh lệ thuộc bảng locations)
            $table->json('pickup_snapshot')->nullable();
            $table->json('dropoff_snapshot')->nullable();
        
            $table->text('pickup_address')->nullable();
            $table->text('dropoff_address')->nullable();
        
            // Thanh toán
            $table->string('currency', 10)->default('VND');
            $table->decimal('total_price', 12, 0)->default(0);
            $table->decimal('discount_amount', 12, 0)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
        
            // Người đặt hộ
            $table->string('booker_name')->nullable();
            $table->string('booker_phone')->nullable();
        
            $table->text('notes')->nullable();
            $table->json('passenger_info')->nullable();
        
            // Trạng thái & thời gian
            $table->enum('status', ['pending','paying','paid','canceled','expired'])
                  ->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('completed_at')->nullable();
        
            // Liên kết booking sau khi hoàn tất (tùy chọn)
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
        
            $table->timestamps();
        
            // Gợi ý: tránh nhiều draft “mở” cho cùng session+trip.
            // MySQL không có partial unique dễ dàng; enforce ở tầng service:
            // - Khi tạo draft mới cho (trip_id, session_token), nếu đã có draft/paying chưa đóng -> return draft cũ.
            $table->index(['trip_id', 'status', 'expires_at']);
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
