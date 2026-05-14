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
        Schema::create('veriants', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable();
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('quantity')->nullable();
            $table->string('additional_price')->nullable();
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
        Schema::dropIfExists('veriants');
    }
};
