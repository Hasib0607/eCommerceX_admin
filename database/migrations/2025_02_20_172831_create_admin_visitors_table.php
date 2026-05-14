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
        Schema::create('admin_visitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('store_url')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('page_url')->nullable();
            $table->string('page_title')->nullable();
            $table->string('refer_page_url')->nullable();
            $table->string('ip')->nullable();
            $table->string('device')->nullable();
            $table->string('mac')->nullable();
            $table->string('os')->nullable();
            $table->string('browser')->nullable();
            $table->string('country_code')->nullable();
            $table->string('country_name')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('location')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('category_id')->nullable();
            $table->string('product_id')->nullable();
            $table->string('visit_time')->nullable();
            $table->string('exit_time')->nullable();
            $table->string('time_zone')->nullable();
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
        Schema::dropIfExists('admin_visitors');
    }
};
