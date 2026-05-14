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
        Schema::create('courier_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('courier_name');
            $table->string('courier_store_id')->nullable();
            $table->string('consignment_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('merchant_order_id')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('delivery_fee')->nullable();
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
        Schema::dropIfExists('courier_deliveries');
    }
};
