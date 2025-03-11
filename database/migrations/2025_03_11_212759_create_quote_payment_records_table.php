
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

// Renamed class with a unique suffix to avoid conflicts
class CreateQuotePaymentsTable20250311 extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up()
    {
        // Log the class name for debugging
        Log::info('Running migration with class: ' . __CLASS__);
        Log::info('Starting migration: creating quote_payments table');
        
        Schema::create('quote_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('quote_uuid')->index(); // UUID from domain_results
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // User who requested the quote
            $table->enum('status', ['pending', 'paid', 'cancelled', 'refunded'])->default('pending');
            $table->decimal('amount', 10, 2)->nullable(); // Total amount paid
            $table->foreignId('processed_by')->nullable()->references('id')->on('users')->onDelete('set null'); // Admin who processed
            $table->text('admin_notes')->nullable(); // Notes from admin
            $table->timestamp('paid_at')->nullable(); // When payment was made
            $table->timestamps(); // created_at and updated_at
            
            // Add a unique constraint on quote_uuid to ensure one payment record per quote
            $table->unique('quote_uuid');
            
            Log::info('Created quote_payments table');
        });
        
        // Add a trigger or logic to automatically create a payment record when domain results are created
        // This could be done in the application code instead
        
        Log::info('Migration complete for quote_payments table');
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down()
    {
        Log::info('Rolling back: dropping quote_payments table');
        
        Schema::dropIfExists('quote_payments');
        
        Log::info('Dropped quote_payments table');
    }
}
