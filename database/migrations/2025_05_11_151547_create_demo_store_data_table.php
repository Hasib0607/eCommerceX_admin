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
        Schema::create('demo_store_data', function (Blueprint $table) {
            $table->id();
            $table->string('product_name')->nullable();
            $table->string('product_image')->nullable();
            $table->string('category_name')->nullable();
            $table->string('category_image')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('slider_image')->nullable();
            $table->string('theme_value')->nullable();
            $table->string('header_color')->nullable();
            $table->string('type');
            $table->string('category_id')->nullable();
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
        Schema::dropIfExists('demo_store_data');
    }
};
