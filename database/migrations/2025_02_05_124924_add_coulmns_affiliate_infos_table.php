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
        Schema::table('affiliate_infos', function (Blueprint $table) {
            $table->decimal('affiliate_charge_usd')->after('affiliate_charge')->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('affiliate_infos', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_infos', 'affiliate_charge_usd')) {
                $table->dropColumn('affiliate_charge_usd');
            }
        });
    }
};
