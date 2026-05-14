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
        Schema::create('order_transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('transactionId')->nullable();
            $table->decimal('amountPaid')->default(0);
            $table->decimal('merchant_processing_ratio')->default(0);
            $table->decimal('merchant_processing_charge')->default(0);
            $table->decimal('merchant_amount')->default(0);
            $table->string('currency')->default('BDT');
            $table->string('payment_type')->nullable();
            $table->string('cardnumber')->nullable();
            $table->string('bank_trxid')->nullable();
            $table->string('approval_code')->nullable();
            $table->string('payment_processor')->nullable();
            $table->string('date_processed')->nullable();
            $table->string('store_amount')->nullable();
            $table->string('processing_ratio')->nullable();
            $table->string('processing_charge')->nullable();
            $table->string('ip')->nullable();
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
        Schema::dropIfExists('order_transaction_histories');
    }
};
