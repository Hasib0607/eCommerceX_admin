<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ebt_analytics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('store_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->string('device')->nullable();
            $table->string('ip')->nullable();
            $table->string('mac')->nullable();
            $table->string('url')->nullable();
            $table->string('city')->nullable();
            $table->string('country_code')->nullable();
            $table->string('country_name')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('postal')->nullable();
            $table->string('state')->nullable();
            $table->text('location')->nullable();
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
        Schema::dropIfExists('ebt_analytics');
    }
};
