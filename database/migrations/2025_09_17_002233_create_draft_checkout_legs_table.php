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
        Schema::create('draft_checkout_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_checkout_id')->constrained('draft_checkouts')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->enum('leg', ['OUT', 'RETURN']);
            $table->decimal('total_price', 12, 0)->default(0);
            $table->foreignId('pickup_location_id')->nullable()
                ->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()
                ->constrained('locations')->nullOnDelete();

            // Snapshot tên/địa chỉ điểm đón/trả tại thời điểm đặt (tránh lệ thuộc bảng locations)
            $table->json('pickup_snapshot')->nullable();
            $table->json('dropoff_snapshot')->nullable();

            $table->text('pickup_address')->nullable();
            $table->text('dropoff_address')->nullable();
            $table->timestamps();

            $table->unique(['draft_checkout_id', 'trip_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_checkout_legs');
    }
};
