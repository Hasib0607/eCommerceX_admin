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
        Schema::create('superstaff_sales_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->onDelete('cascade');
            $table->unsignedBigInteger('staff_id');
            $table->decimal('new_commission')->default(0.00);
            $table->decimal('renew_commission')->default(0.00);
            $table->decimal('setup_commission')->default(0.00);
            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('superstaffs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('superstaff_sales_commissions');
    }
};
