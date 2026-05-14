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
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('courier_name');
            $table->string('courier_store_id')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->string('access_token')->nullable();
            $table->bigInteger('store_id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->tinyInteger('status')->default(0)->comment("0=Inactive|1=Active");
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
        Schema::dropIfExists('couriers');
    }
};
