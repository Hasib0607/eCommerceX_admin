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
        Schema::create('holdorders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable();
            $table->string('oids')->nullable();
            $table->string('uid')->nullable();
            $table->string('subtotal')->nullable();
            $table->string('discount')->nullable();
            $table->string('tax')->nullable();
            $table->string('shipping')->nullable();
            $table->string('other_charge')->nullable();
            $table->string('payable_amount')->nullable();
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
        Schema::dropIfExists('holdorders');
    }
};
