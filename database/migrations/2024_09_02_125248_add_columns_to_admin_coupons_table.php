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
        Schema::table('admin_coupons', function (Blueprint $table) {
            $table->string('currency_type', 10)->default('BDT')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('admin_coupons', 'currency_type')) {
                $table->dropColumn('currency_type');
            }
        });
    }
};
