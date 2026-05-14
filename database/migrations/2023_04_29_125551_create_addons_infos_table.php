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
        Schema::create('addons_infos', function (Blueprint $table) {
            $table->id();
            $table->integer('addons_id')->nullable();
            $table->string('type')->nullable();
            $table->string('name')->nullable();
            $table->string('month_or_value')->nullable();
            $table->string('price')->nullable();
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
        Schema::dropIfExists('addons_infos');
    }
};
