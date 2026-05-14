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
        Schema::table('coupons', function (Blueprint $table) {
            $table->integer('shipping_area')->after('max_use')->nullable();
            $table->tinyInteger('auto_apply')->after('shipping_area')->default(0)->comment("0=Inactive|1=Active");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'shipping_area')) {
                $table->dropColumn('shipping_area');
            }
            if (Schema::hasColumn('coupons', 'auto_apply')) {
                $table->dropColumn('auto_apply');
            }
        });
    }
};
