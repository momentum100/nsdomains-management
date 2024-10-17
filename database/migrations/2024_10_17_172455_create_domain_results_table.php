<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainResultsTable extends Migration
{
    public function up()
    {
        Schema::create('domain_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('domain');
            $table->string('registrant')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('days_left');
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('domain_results');
    }
}
