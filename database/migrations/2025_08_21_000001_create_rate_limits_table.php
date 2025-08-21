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
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key');                           // Key để track (user_id, ip, action)
            $table->string('action');                        // Hành động (birthday_change, coupon_request, etc.)
            $table->integer('attempts')->default(1);         // Số lần thực hiện
            $table->timestamp('first_attempt_at')->nullable();           // Lần đầu thực hiện
            $table->timestamp('last_attempt_at')->nullable();            // Lần cuối thực hiện
            $table->timestamp('reset_at')->nullable();                   // Thời điểm reset
            $table->boolean('is_blocked')->default(false);   // Có bị block không
            $table->text('block_reason')->nullable();        // Lý do bị block
            
            $table->timestamps();
            
            // Index để tối ưu truy vấn
            $table->index(['key', 'action']);
            $table->index(['reset_at']);
            $table->index(['is_blocked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limits');
    }
};
