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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('plan_id')->nullable();
            $table->string('template_id')->nullable();
            $table->string('logo')->nullable();
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('referred_by')->nullable();
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
        Schema::dropIfExists('customers');
    }
};
