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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('bus_id')->nullable(); 
            $table->dateTime('departure_time');
            $table->enum('status', ['scheduled','running','finished','cancelled'])->default('scheduled');
            $table->timestamps();
        
            $table->foreign('route_id')->references('id')->on('routes')->cascadeOnDelete();
            $table->foreign('bus_id')->references('id')->on('buses')->nullOnDelete();
            $table->unique(['route_id', 'bus_id', 'departure_time'], 'trips_unique');

            
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
