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
            $table->string('code')->unique();
            $table->enum('discount_type', ['percentage','fixed_amount']);
            $table->decimal('discount_value', 10, 2);
            $table->unsignedSmallInteger('per_user_limit')->default(1); // giới hạn mặc định / user
            $table->unsignedInteger('total_limit')->nullable();         // giới hạn tổng (optional)
            $table->unsignedInteger('used_count')->default(0);          // tổng đã dùng (optional)
            $table->enum('status', ['active','disabled','expired'])->default('active');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
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
