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
        Schema::table('login_history', function (Blueprint $table) {
            $table->index(['email', 'created_at'], 'login_history_email_created_at_idx');
            $table->index('created_at', 'login_history_created_at_idx');
        });

        Schema::table('telegram_user_commands', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'telegram_user_commands_user_created_at_idx');
            $table->index('created_at', 'telegram_user_commands_created_at_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'transactions_user_created_at_idx');
            $table->index(['type', 'created_at'], 'transactions_type_created_at_idx');
            $table->index('created_at', 'transactions_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_history', function (Blueprint $table) {
            $table->dropIndex('login_history_email_created_at_idx');
            $table->dropIndex('login_history_created_at_idx');
        });

        Schema::table('telegram_user_commands', function (Blueprint $table) {
            $table->dropIndex('telegram_user_commands_user_created_at_idx');
            $table->dropIndex('telegram_user_commands_created_at_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_created_at_idx');
            $table->dropIndex('transactions_type_created_at_idx');
            $table->dropIndex('transactions_created_at_idx');
        });
    }
};
