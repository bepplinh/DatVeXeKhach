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
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('used_count')->default(0);      // đã dùng mấy lần
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedSmallInteger('per_user_limit_override')->nullable(); // optional
            $table->unique(['user_id','coupon_id']); // chốt 1 dòng / cặp
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_user');
    }
};
