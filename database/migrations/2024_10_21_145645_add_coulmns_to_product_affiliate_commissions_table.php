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
        Schema::table('product_affiliate_commissions', function (Blueprint $table) {
            $table->double('product_price')->after('product_id')->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_affiliate_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('product_affiliate_commissions', 'product_price')) {
                $table->dropColumn('product_price');
            }
        });
    }
};
