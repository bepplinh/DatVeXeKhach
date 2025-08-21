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
        Schema::create('user_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('field_name');                    // Tên trường thay đổi (birthday, email, etc.)
            $table->text('old_value')->nullable();           // Giá trị cũ
            $table->text('new_value')->nullable();           // Giá trị mới
            $table->string('ip_address')->nullable();        // IP của user khi thay đổi
            $table->string('user_agent')->nullable();        // User agent
            $table->timestamp('changed_at')->nullable();                 // Thời điểm thay đổi
            $table->boolean('is_suspicious')->default(false); // Đánh dấu nếu có hành vi đáng ngờ
            $table->text('suspicious_reason')->nullable();   // Lý do đánh dấu đáng ngờ
            
            $table->timestamps();
            
            // Index để tối ưu truy vấn
            $table->index(['user_id', 'field_name']);
            $table->index(['user_id', 'changed_at']);
            $table->index(['is_suspicious']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_change_logs');
    }
};
