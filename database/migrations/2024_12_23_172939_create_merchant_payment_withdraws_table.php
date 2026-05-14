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
        Schema::create('merchant_payment_withdraws', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->onDelete('cascade');
            $table->foreignId("store_id")->constrained("stores")->onDelete('cascade');
            $table->string('withdraw_amount')->default(0);
            $table->tinyInteger('status')->default(0)->comment('0=Pending|1=Approved|2=Completed|3=Rejected');
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
        Schema::dropIfExists('merchant_payment_withdraws');
    }
};
