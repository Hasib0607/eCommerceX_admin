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
        Schema::create('planorders', function (Blueprint $table) {
            $table->id();
            $table->string('plan_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->string('active_date')->nullable();
            $table->string('expiry_date')->nullable();
            $table->string('total_amount')->nullable();
            $table->string('total_month')->nullable();
            $table->string('status')->nullable();
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
        Schema::dropIfExists('planorders');
    }
};
