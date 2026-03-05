<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'telegram_user_id')) {
            return;
        }

        if (!$this->indexExists('users', 'users_telegram_user_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('telegram_user_id', 'users_telegram_user_id_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if ($this->indexExists('users', 'users_telegram_user_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_telegram_user_id_unique');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne(
            'select 1 from pg_indexes where tablename = ? and indexname = ? limit 1',
            [$table, $index]
        );

        return $result !== null;
    }
};
