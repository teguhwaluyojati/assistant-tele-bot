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
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'telegram_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('telegram_user_id')->nullable()->after('email');
            });
        }

        if (!$this->constraintExists('users', 'users_telegram_user_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('telegram_user_id')->references('id')->on('telegram_users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'telegram_user_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if ($this->constraintExists('users', 'users_telegram_user_id_foreign')) {
                $table->dropForeign(['telegram_user_id']);
            }

            $table->dropColumn('telegram_user_id');
        });
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        $result = DB::selectOne(
            'select 1 from information_schema.table_constraints where table_name = ? and constraint_name = ? limit 1',
            [$table, $constraint]
        );

        return $result !== null;
    }
};
