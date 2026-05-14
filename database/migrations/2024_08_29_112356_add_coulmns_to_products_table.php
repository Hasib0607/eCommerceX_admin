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
        Schema::table('products', function (Blueprint $table) {
            $table->tinyInteger('discount_product')->default(0)->after('promotional_price');
            $table->string('prev_discount')->nullable()->after('discount_product');
            $table->date('expiry_date')->nullable()->after('ask_price');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_product', 'prev_discount', 'expiry_date']);
        });
    }
};
