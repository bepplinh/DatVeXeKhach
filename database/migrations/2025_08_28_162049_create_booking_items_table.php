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
        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('seat_id')->nullable(); // nếu chọn chỗ ngồi
            $table->unsignedBigInteger('origin_location_id');  // điểm đón
            $table->unsignedBigInteger('destination_location_id'); // điểm trả
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();

            $table->integer('price'); // giá của từng vé

            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('seat_id')->references('id')->on('seats')->nullOnDelete();
            $table->foreign('origin_location_id')->references('id')->on('locations');
            $table->foreign('destination_location_id')->references('id')->on('locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
    }
};
