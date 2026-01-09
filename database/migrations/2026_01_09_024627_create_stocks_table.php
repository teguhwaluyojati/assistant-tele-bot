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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // Kode Saham (BBCA, GOTO)
            $table->string('name')->nullable();   // Nama Perusahaan
            $table->boolean('is_active')->default(true); // Status aktif/delisted
            $table->string('board')->nullable(); // Isi: 'Utama', 'Pengembangan', dll
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
