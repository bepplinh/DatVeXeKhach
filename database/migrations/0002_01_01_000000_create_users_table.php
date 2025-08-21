<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('username')->unique();          // cho đăng nhập username
            $t->string('email')->nullable()->unique(); // FB có thể không trả email
            $t->string('phone')->nullable()->unique();
            $t->date('birthday')->nullable();
            $t->enum('role', ['customer','staff','admin'])->default('customer');
            $t->string('avatar')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password')->nullable();        // social có thể không có password cb 
            $t->rememberToken();
            $t->timestamps();
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
