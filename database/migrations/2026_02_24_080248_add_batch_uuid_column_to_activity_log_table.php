<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBatchUuidColumnToActivityLogTable extends Migration
{
    public function up()
    {
        if (!Schema::connection(config('activitylog.database_connection'))->hasTable(config('activitylog.table_name'))) {
            return;
        }

        if (!Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'batch_uuid')) {
            Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            });
        }
    }

    public function down()
    {
        if (!Schema::connection(config('activitylog.database_connection'))->hasTable(config('activitylog.table_name'))) {
            return;
        }

        if (Schema::connection(config('activitylog.database_connection'))->hasColumn(config('activitylog.table_name'), 'batch_uuid')) {
            Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
                $table->dropColumn('batch_uuid');
            });
        }
    }
}
