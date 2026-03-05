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
        if (!Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'category')) {
                $table->string('category', 100)->nullable()->after('description');
            }

            if (!Schema::hasColumn('transactions', 'category_source')) {
                $table->string('category_source', 20)->nullable()->after('category');
            }

            if (!Schema::hasColumn('transactions', 'category_confidence')) {
                $table->decimal('category_confidence', 5, 2)->nullable()->after('category_source');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'category_confidence')) {
                $table->dropColumn('category_confidence');
            }

            if (Schema::hasColumn('transactions', 'category_source')) {
                $table->dropColumn('category_source');
            }

            if (Schema::hasColumn('transactions', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
