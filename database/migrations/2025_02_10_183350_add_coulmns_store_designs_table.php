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
        Schema::table('store_designs', function (Blueprint $table) {
            $table->tinyInteger('is_buy_now_cart')->after('image_description')->default(0);
            $table->tinyInteger('is_buy_now_cart1')->after('is_buy_now_cart')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_designs', function (Blueprint $table) {
            if (Schema::hasColumn('store_designs', 'is_buy_now_cart')) {
                $table->dropColumn('is_buy_now_cart');
            }
            if (Schema::hasColumn('store_designs', 'is_buy_now_cart1')) {
                $table->dropColumn('is_buy_now_cart1');
            }
        });
    }
};
