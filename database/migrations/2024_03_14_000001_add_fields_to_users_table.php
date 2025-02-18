<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToUsersTable extends Migration
{
    /**
     * Add namepros_name, payment_details, and is_admin fields to users table
     * 
     * namepros_name: User's NamePros forum username
     * payment_details: User's payment information in text format
     * is_admin: Boolean flag to identify admin users
     */
    public function up()
    {
        // Log the start of migration
        \Log::info('Starting migration: Adding fields to users table');
        
        Schema::table('users', function (Blueprint $table) {
            // Add NamePros username field
            $table->string('namepros_name')->nullable()->after('name');
            
            // Add payment details field with template support
            $table->text('payment_details')->nullable()->after('namepros_name');
            
            // Add admin flag with false default
            $table->boolean('is_admin')->default(false)->after('payment_details');
        });
        
        // Log completion
        \Log::info('Completed migration: Fields added to users table');
    }

    /**
     * Reverse the migrations
     */
    public function down()
    {
        // Log the start of rollback
        \Log::info('Starting rollback: Removing added fields from users table');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'namepros_name',
                'payment_details',
                'is_admin'
            ]);
        });
        
        // Log completion of rollback
        \Log::info('Completed rollback: Fields removed from users table');
    }
} 