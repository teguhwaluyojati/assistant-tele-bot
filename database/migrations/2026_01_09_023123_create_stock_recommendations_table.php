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
        Schema::create('stock_recommendations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 10);      // Kode Saham (BBCA, BUMI)
                $table->double('price');         // Harga Terakhir
                $table->integer('score');        // Skor Analisa Kita
                $table->string('signal');        // STRONG BUY / BUY
                $table->string('buy_area');      // Rentang Beli
                $table->double('tp_target');     // Target Harga
                $table->double('cl_price');      // Cut Loss
                $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_recommendations');
    }
};
