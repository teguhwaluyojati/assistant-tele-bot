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
        if (Schema::hasTable('login_history')) {
            Schema::table('login_history', function (Blueprint $table) {
                if (!$this->indexExists('login_history', 'login_history_email_created_at_idx')) {
                    $table->index(['email', 'created_at'], 'login_history_email_created_at_idx');
                }

                if (!$this->indexExists('login_history', 'login_history_created_at_idx')) {
                    $table->index('created_at', 'login_history_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('telegram_user_commands')) {
            Schema::table('telegram_user_commands', function (Blueprint $table) {
                if (!$this->indexExists('telegram_user_commands', 'telegram_user_commands_user_created_at_idx')) {
                    $table->index(['user_id', 'created_at'], 'telegram_user_commands_user_created_at_idx');
                }

                if (!$this->indexExists('telegram_user_commands', 'telegram_user_commands_created_at_idx')) {
                    $table->index('created_at', 'telegram_user_commands_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!$this->indexExists('transactions', 'transactions_user_created_at_idx')) {
                    $table->index(['user_id', 'created_at'], 'transactions_user_created_at_idx');
                }

                if (!$this->indexExists('transactions', 'transactions_type_created_at_idx')) {
                    $table->index(['type', 'created_at'], 'transactions_type_created_at_idx');
                }

                if (!$this->indexExists('transactions', 'transactions_created_at_idx')) {
                    $table->index('created_at', 'transactions_created_at_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('login_history')) {
            Schema::table('login_history', function (Blueprint $table) {
                if ($this->indexExists('login_history', 'login_history_email_created_at_idx')) {
                    $table->dropIndex('login_history_email_created_at_idx');
                }

                if ($this->indexExists('login_history', 'login_history_created_at_idx')) {
                    $table->dropIndex('login_history_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('telegram_user_commands')) {
            Schema::table('telegram_user_commands', function (Blueprint $table) {
                if ($this->indexExists('telegram_user_commands', 'telegram_user_commands_user_created_at_idx')) {
                    $table->dropIndex('telegram_user_commands_user_created_at_idx');
                }

                if ($this->indexExists('telegram_user_commands', 'telegram_user_commands_created_at_idx')) {
                    $table->dropIndex('telegram_user_commands_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if ($this->indexExists('transactions', 'transactions_user_created_at_idx')) {
                    $table->dropIndex('transactions_user_created_at_idx');
                }

                if ($this->indexExists('transactions', 'transactions_type_created_at_idx')) {
                    $table->dropIndex('transactions_type_created_at_idx');
                }

                if ($this->indexExists('transactions', 'transactions_created_at_idx')) {
                    $table->dropIndex('transactions_created_at_idx');
                }
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
