<?php
// database/migrations/2024_09_24_000000_add_status_to_domains_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToDomainsTable extends Migration
{
    public function up()
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE')->after('registrar'); // Add status column
        });
    }

    public function down()
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}