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
        Schema::create('marchant_payment_getway_k_y_c_s', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('nid')->nullable();
            $table->string('nid_front')->nullable();
            $table->string('nid_back')->nullable();
            $table->string('current_bill_copy')->nullable();
            $table->string('dbid')->nullable();
            $table->string('dbid_front')->nullable();
            $table->string('dbid_back')->nullable();
            $table->string('trade_licence')->nullable();
            $table->string('trade_licence_image')->nullable();
            $table->string('tin')->nullable();
            $table->string('tin_image')->nullable();
            $table->string('bin')->nullable();
            $table->string('bin_image')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('online_bank')->nullable();
            $table->tinyInteger('status')->default(0)->comment("0=Unapproved|1=Approved|2=Rejected");
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
        Schema::dropIfExists('marchant_payment_getway_k_y_c_s');
    }
};
