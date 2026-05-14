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
        Schema::create('store_purchase_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('store_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('plan_id')->nullable();
            $table->unsignedInteger('plan_month')->nullable();
            $table->decimal('plan_price', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('active_date')->nullable();
            $table->string('expire_date')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
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
        Schema::dropIfExists('store_purchase_histories');
    }
};
