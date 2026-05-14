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
        Schema::create('addons_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('store_id')->nullable();
            $table->json('addons')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->json('combo_packages')->nullable();
            $table->integer('plan_id')->nullable();
            $table->string('plan_type')->nullable();
            $table->string('plan_month')->nullable();
            $table->integer('total')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('addons_orders');
    }
};
