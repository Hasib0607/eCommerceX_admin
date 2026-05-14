<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('countryCode', 2)->nullable();
            $table->string('countryName', 100)->nullable();
            $table->string('currencyCode', 3)->nullable();
            $table->string('fipsCode', 2)->nullable();
            $table->string('isoNumeric', 4)->nullable();
            $table->string('north', 30)->nullable();
            $table->string('south', 30)->nullable();
            $table->string('east', 30)->nullable();
            $table->string('west', 30)->nullable();
            $table->string('capital', 100)->nullable();
            $table->string('continentName', 100)->nullable();
            $table->string('continent', 2)->nullable();
            $table->string('languages', 100)->nullable();
            $table->string('isoAlpha3', 3)->nullable();
            $table->integer('geonameId', false, true)->length(10)->nullable();
            $table->string('telephonePrefix', 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
};
