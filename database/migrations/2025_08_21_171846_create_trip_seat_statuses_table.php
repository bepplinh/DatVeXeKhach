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
            $table->boolean('is_booked')->default(false);
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('lock_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id','seat_id']);
            $table->index(['trip_id','is_booked']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('trip_seat_statuses');
    }
};
