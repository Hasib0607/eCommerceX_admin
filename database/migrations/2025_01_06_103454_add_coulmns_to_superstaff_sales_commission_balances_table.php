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
        Schema::table('superstaff_sales_commission_balances', function (Blueprint $table) {
            $table->string('store_id')->after('staff_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('superstaff_sales_commission_balances', function (Blueprint $table) {
            if (Schema::hasColumn('superstaff_sales_commission_balances', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }
};
