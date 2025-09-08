<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('trip_seat_statuses', function (Blueprint $table) {
            $table->id();
        
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
        
            // Trạng thái cứng:
            $table->boolean('is_booked')->default(false);
            $table->foreignId('booked_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamp('booked_at')->nullable();
        
            // Lock khi đi checkout:
            $table->foreignId('locked_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();   
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('lock_expires_at')->nullable();
        
            $table->timestamps();
        
            // Không cho 2 bản ghi cùng (trip,seat)
            $table->unique(['trip_id','seat_id']);
        
            // Index phục vụ truy vấn phổ biến
            $table->index(['trip_id','is_booked']);  
            $table->index(['trip_id','seat_id']);     
            $table->index(['trip_id','lock_expires_at']);  
        });
        
    }
    public function down(): void {
        Schema::dropIfExists('trip_seat_statuses');
    }
};
