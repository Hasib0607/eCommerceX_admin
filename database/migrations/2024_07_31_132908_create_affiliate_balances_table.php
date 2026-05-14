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
        Schema::create('affiliate_balances', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->integer('total_earning')->default(0);
            $table->integer('balance')->default(0);
            $table->integer('withdraw_request_amount')->default(0);
            $table->tinyInteger('withdraw_request_status')->default(0)->comment("0=Pending|1=Approved|2=Rejected");
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
        Schema::dropIfExists('affiliate_balances');
    }
};
