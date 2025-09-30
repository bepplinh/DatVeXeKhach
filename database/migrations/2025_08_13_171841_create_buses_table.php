<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('plate_number')->unique();
            $table->foreignId('type_bus_id')->constrained('type_buses')->onDelete('cascade');
            $table->foreignId('seat_layout_template_id')->nullable()->constrained('seat_layout_templates')->cascadeOnDelete()->onDelete('cascade');
            $table->boolean('uses_custom_seats')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('buses');
    }
};
