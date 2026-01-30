<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('tb_scalping_alerts', function (Blueprint $table) {
            $table->id();

            $table->string('code', 10)->index(); 
            $table->unsignedTinyInteger('score')->index(); 

            $table->timestamp('alerted_at')->index();

            $table->decimal('entry_price', 12, 2)->nullable();
            $table->decimal('tp_price', 12, 2)->nullable();
            $table->decimal('cl_price', 12, 2)->nullable();
            $table->boolean('is_hit_tp')->nullable();
            $table->boolean('is_hit_cl')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tb_scalping_alerts');
    }
};
