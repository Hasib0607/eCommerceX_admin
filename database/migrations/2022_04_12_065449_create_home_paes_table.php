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
        Schema::create('home_paes', function (Blueprint $table) {
            $table->id();
            $table->string('slider')->nullable();
            $table->string('banner')->nullable();
            $table->string('new_arrival')->nullable();
            $table->string('offer')->nullable();
            $table->string('trends_product')->nullable();
            $table->string('client_section')->nullable();
            $table->string('testimonials')->nullable();
            $table->string('newslatter')->nullable();
            $table->string('privacy_policy')->nullable();
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
        Schema::dropIfExists('home_paes');
    }
};
