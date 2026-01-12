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
        Schema::create('day_trade_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);      // Kode Saham
            $table->double('price');         // Harga Close
            $table->double('change_pct');    // % Kenaikan (Penting utk ranking)
            $table->string('signal');        // Label: BSJP / MOMENTUM
            $table->string('buy_area');      // Area Beli
            $table->double('tp_target');     // Target Profit
            $table->double('cl_price');      // Cut Loss
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_trade_recommendations');
    }
};
