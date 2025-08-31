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
        // Schema::create('telegram_users', function (Blueprint $table) {
        //      $table->id(); // ID internal database Anda
        // $table->bigInteger('user_id')->unique(); // ID unik dari Telegram
        // $table->string('username')->nullable();
        // $table->string('first_name')->nullable();
        // $table->string('last_name')->nullable();
        // $table->timestamp('last_interaction_at')->nullable();
        // $table->timestamps(); // created_at dan updated_at
                Schema::table('telegram_users', function (Blueprint $table) {
            // Menambahkan kolom state setelah kolom username (bisa disesuaikan)
            $table->string('state')->default('normal')->after('username')->nullable();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('telegram_users');
                Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
