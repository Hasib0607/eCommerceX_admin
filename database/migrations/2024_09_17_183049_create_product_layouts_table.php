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
        Schema::create('product_layouts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->unsigned()->index();
            $table->bigInteger('store_id')->unsigned()->index();
            $table->bigInteger('layout_design_id')->unsigned()->nullable();
            $table->longText('text')->nullable();
            $table->string('link')->nullable();
            $table->string('button',50)->nullable();
            $table->string('type',50)->index();
            $table->integer('position');
            $table->foreign('layout_design_id')->references('id')->on('layout_designs')->onDelete('cascade');
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
        Schema::dropIfExists('product_layouts');
    }
};
