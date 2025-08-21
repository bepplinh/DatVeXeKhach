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
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // tên văn phòng  (VD: Văn phòng Hà Nội)       // FK tới bảng locations (xã/huyện/tỉnh)
            $table->string('address');                       // địa chỉ cụ thể (số nhà, đường)
            $table->string('phone')->nullable();             // số điện thoại liên hệ
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
