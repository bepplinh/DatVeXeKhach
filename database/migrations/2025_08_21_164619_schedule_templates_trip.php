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
        Schema::create('schedule_template_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('bus_id')->constrained('buses')->onDelete('cascade');
            $table->unsignedTinyInteger('weekday'); // 0=CN, 1=Th2,...6=Th7
            $table->time('departure_time');
            $table->boolean('active')->default(true);

            $table->unique(['bus_id','weekday','departure_time'], 'unique_schedule_template_trip');
            $table->index(['weekday', 'departure_time']);
            $table->index(['bus_id', 'weekday']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
