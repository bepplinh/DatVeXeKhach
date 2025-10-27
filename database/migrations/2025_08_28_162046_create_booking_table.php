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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); 
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->decimal('subtotal_price', 10, 0)->default(0);
            $table->decimal('total_price', 10, 0)->default(0);
            $table->decimal('discount_amount', 10, 0)->default(0);
            
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');

            $table->enum('payment_provider', ['cash', 'payos'])->nullable();
            $table->string('payment_intent_id', 100)->nullable();
            $table->unique(['payment_provider', 'payment_intent_id']);

            $table->string('passenger_name')->nullable();
            $table->string('passenger_phone')->nullable();
            $table->string('passenger_email')->nullable();

            $table->unsignedBigInteger('origin_location_id');  // điểm đón
            $table->unsignedBigInteger('destination_location_id'); // điểm trả
            $table->foreign('origin_location_id')->references('id')->on('locations');
            $table->foreign('destination_location_id')->references('id')->on('locations');
            $table->string('pickup_address')->nullable();
            $table->string('dropoff_address')->nullable();

            $table->timestamps();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
