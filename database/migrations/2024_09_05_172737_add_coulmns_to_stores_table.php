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
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedInteger('dropship_commission')->after('expiry_date')->default(3);
            $table->tinyInteger('order_pull')->after('dropship_commission')->default(0)->comment("0=order place|1=order delivered");
            $table->decimal('overflow_commission')->after('order_pull')->default(10000);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['dropship_commission', 'order_pull', 'overflow_commission']);
        });
    }
};
