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
        Schema::create('bkash_infos', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable();
            $table->string('BKASH_CHECKOUT_URL_USER_NAME')->nullable();
            $table->string('BKASH_CHECKOUT_URL_PASSWORD')->nullable();
            $table->string('BKASH_CHECKOUT_URL_APP_KEY')->nullable();
            $table->string('BKASH_CHECKOUT_URL_APP_SECRET')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bkash_infos');
    }
};
