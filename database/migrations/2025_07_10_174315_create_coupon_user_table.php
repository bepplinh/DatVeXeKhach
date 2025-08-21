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
        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_used')->default(false);      // User đã sử dụng mã này chưa
            $table->timestamp('used_at')->nullable();        // Thời điểm user dùng coupon (nếu có)
            
            // === CÁC TRƯỜNG BẢO MẬT ===
            $table->string('birthday_hash')->nullable();     // Hash của ngày sinh nhật khi tạo coupon
            $table->timestamp('received_at')->nullable();    // Thời điểm nhận coupon
            $table->string('ip_address')->nullable();        // IP của user khi nhận coupon
            $table->string('user_agent')->nullable();        // User agent khi nhận coupon
            $table->boolean('is_suspicious')->default(false); // Đánh dấu nếu có hành vi đáng ngờ
            $table->text('suspicious_reason')->nullable();   // Lý do đánh dấu đáng ngờ
            
            $table->timestamps();
            
            // Index để tối ưu truy vấn
            $table->index(['user_id', 'birthday_hash']);
            $table->index(['user_id', 'received_at']);
            $table->unique(['user_id', 'coupon_id', 'birthday_hash']); // Ngăn nhận trùng lặp
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user');
    }
};
