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
        Schema::table('superstaff_sales_commissions', function (Blueprint $table) {
            $table->string('setup_amount')->after('setup_commission')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('superstaff_sales_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('superstaff_sales_commissions', 'setup_amount')) {
                $table->dropColumn('setup_amount');
            }
        });
    }
};
