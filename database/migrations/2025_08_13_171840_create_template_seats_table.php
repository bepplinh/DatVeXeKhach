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
        Schema::create('template_seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seat_layout_template_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('deck')->default(1);
            $t->enum('column_group', ['right','middle','left']);
            $t->unsignedTinyInteger('index_in_column');
            $t->string('seat_label');    
            $t->timestamps();
            
            $t->unique(
                ['seat_layout_template_id','deck','column_group','index_in_column'],
                'tpl_seat_unique'
            );
          });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_seats');
    }
};
