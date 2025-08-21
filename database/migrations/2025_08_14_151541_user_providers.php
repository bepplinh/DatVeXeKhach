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
        Schema::create('user_providers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('provider');    // 'google' | 'facebook' | 'apple'...
            $t->string('provider_id'); // id bên provider
            $t->string('access_token')->nullable();
            $t->string('refresh_token')->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->timestamps();
        
            $t->unique(['provider','provider_id']); // định danh duy nhất 1 tài khoản social
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
