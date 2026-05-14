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
        Schema::create('product_affiliate_withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_info_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('note')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('amount')->default(0);
            $table->string('currency')->default('BDT');
            $table->tinyInteger('status')->default(0)->comment('0=Pending|1=Approved|2=Rejected');
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
        Schema::dropIfExists('product_affiliate_withdraw_requests');
    }
};
