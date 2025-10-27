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
        Schema::create('draft_checkout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_checkout_id')->references('id')->on('draft_checkouts')->cascadeOnDelete();
            $table->foreignId('draft_checkout_leg_id')->references('id')->on('draft_checkout_legs')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->string('seat_label');
            $table->unsignedBigInteger('fare_id')->nullable();
            $table->timestamps();

            $table->unique(['draft_checkout_leg_id', 'seat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_checkout_items');
    }
};