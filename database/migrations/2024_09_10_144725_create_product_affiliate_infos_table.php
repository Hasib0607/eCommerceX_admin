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
        Schema::create('product_affiliate_infos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('store_id');
            $table->string('referral_code')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('total_earning', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2)->default(0);
            $table->string('currency')->default('BDT');
            $table->boolean('status')->default(0)->comment('0=Inactive|1=Active');
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
        Schema::dropIfExists('product_affiliate_infos');
    }
};
