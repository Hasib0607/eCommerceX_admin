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
        Schema::create('store_designs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->nullable();
            $table->string('title_color', 50)->nullable();
            $table->string('button', 50)->nullable();
            $table->string('button_color', 50)->nullable();
            $table->string('image_description', 255)->nullable();
            $table->string('type', 50);
            $table->bigInteger('store_id')->unsigned();
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
        Schema::dropIfExists('store_designs');
    }
};
