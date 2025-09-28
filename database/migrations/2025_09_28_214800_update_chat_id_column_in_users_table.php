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
        Schema::table('users', function (Blueprint $table) {
            // chat_id ustunini bigInteger ga o'zgartirish (manfiy qiymatlarni qo'llab-quvvatlash uchun)
            $table->bigInteger('chat_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Orqaga qaytarish uchun unsignedBigInteger ga qaytarish
            $table->unsignedBigInteger('chat_id')->change();
        });
    }
};