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
        Schema::create('superstaff_sales_commission_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->nullable()->constrained("users")->onDelete('cascade');
            $table->unsignedBigInteger('staff_id');
            $table->tinyInteger('isSetup')->default(0)->comment("0=No|1=Yes");
            $table->tinyInteger('isNew')->default(0)->comment("0=No|1=Yes");
            $table->tinyInteger('isRenew')->default(0)->comment("0=No|1=Yes");
            $table->decimal('setup_commission')->default(0.00);
            $table->decimal('new_commission')->default(0.00);
            $table->decimal('renew_commission')->default(0.00);
            $table->decimal('total_amount')->default(0.00);
            $table->decimal('commission_amount')->default(0.00);
            $table->decimal('dr')->default(0.00);
            $table->decimal('cr')->default(0.00);
            $table->decimal('balance')->default(0.00);
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
        Schema::dropIfExists('superstaff_sales_commission_balances');
    }
};
