<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AddUserIdToDomainResultsTable extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        Log::info('Starting migration: adding user_id to domain_results table');
        
        Schema::table('domain_results', function (Blueprint $table) {
            // Adding nullable user_id column with foreign key constraint
            // It's nullable because unregistered users can get quotations too
            $table->foreignId('user_id')->nullable()->after('domain')->constrained()->onDelete('set null');
            
            Log::info('Added user_id column to domain_results table');
        });
        
        // Count how many existing records will be affected
        $count = DB::table('domain_results')->count();
        Log::info("Migration complete. {$count} existing records now have nullable user_id field");
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        Log::info('Rolling back: removing user_id from domain_results table');
        
        Schema::table('domain_results', function (Blueprint $table) {
            // First drop the foreign key constraint
            $table->dropForeign(['user_id']);
            // Then drop the column
            $table->dropColumn('user_id');
            
            Log::info('Removed user_id column from domain_results table');
        });
    }
} 