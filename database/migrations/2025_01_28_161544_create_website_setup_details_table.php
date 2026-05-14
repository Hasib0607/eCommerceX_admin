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
        Schema::create('website_setup_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('facebook_link')->nullable();
            $table->string('instagram_link')->nullable();
            $table->string('mobile_number');
            $table->string('whats_app_number');
            $table->string('youtube_link')->nullable();
            $table->string('email');
            $table->string('delivery_cost');
            $table->string('tax')->nullable();
            $table->string('address')->nullable();
            $table->string('logo');
            $table->string('theme_color');
            $table->longText('short_description')->nullable();
            $table->tinyInteger('update_setting')->default(0)->comment("0=Not update|1=Update");
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
        Schema::dropIfExists('website_setup_details');
    }
};
