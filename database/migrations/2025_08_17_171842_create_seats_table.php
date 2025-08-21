<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->constrained()->cascadeOnDelete();
            $table->string('seat_number'); // A1..D10...
            $table->string('deck')->nullable();
            $table->json('position_meta')->nullable();
            $table->timestamps();
            
            $table->unique(['bus_id','seat_number']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('seats');
    }
};
