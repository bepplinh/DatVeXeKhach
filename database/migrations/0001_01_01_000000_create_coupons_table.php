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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Mã giảm giá
            $table->string('name'); // Tên mã giảm giá
            $table->text('description')->nullable(); // Mô tả
            $table->enum('discount_type', ['fixed', 'percentage']); // Loại giảm giá: cố định hoặc phần trăm
            $table->string('type')->nullable(); // Ví dụ: welcome, loyalty, birthday, flashsale,...
            $table->decimal('discount_value', 10, 2); // Giá trị giảm giá
            $table->decimal('minimum_order_amount', 10, 2)->default(0); // Số tiền đơn hàng tối thiểu
            $table->integer('max_usage')->nullable(); // Số lần sử dụng tối đa
            $table->integer('used_count')->default(0); // Số lần đã sử dụng
            $table->timestamp('valid_from')->nullable(); // Thời gian bắt đầu hiệu lực
            $table->timestamp('valid_until')->nullable(); // Thời gian kết thúc hiệu lực
            $table->boolean('is_active')->default(true); // Trạng thái hoạt động
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
