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
            $table->tinyInteger('pay_status')->after('renew_commission')->default(0)->comment("0=Unpaid|1=Paid");
            $table->integer('commission_id')->after('pay_status')->nullable();
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
            if (Schema::hasColumn('superstaff_sales_commission_balances', 'pay_status')) {
                $table->dropColumn('pay_status');
            }
            if (Schema::hasColumn('superstaff_sales_commission_balances', 'commission_id')) {
                $table->dropColumn('commission_id');
            }
        });
    }
};
