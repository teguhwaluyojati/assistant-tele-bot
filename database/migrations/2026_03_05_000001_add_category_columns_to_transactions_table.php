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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('description');
            $table->string('category_source', 20)->nullable()->after('category');
            $table->decimal('category_confidence', 5, 2)->nullable()->after('category_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['category', 'category_source', 'category_confidence']);
        });
    }
};
