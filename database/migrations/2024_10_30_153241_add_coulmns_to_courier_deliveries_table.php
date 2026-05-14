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
        Schema::table('courier_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->after('merchant_order_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('courier_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('courier_deliveries', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }
};
