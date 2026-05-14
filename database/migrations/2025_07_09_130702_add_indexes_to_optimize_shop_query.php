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
        Schema::table('stores', function (Blueprint $table) {
            $table->index('expiry_date', 'idx_expiry');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('regular_price', 'idx_price');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('product_id', 'idx_product_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('idx_expiry');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_price');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('idx_product_id');
        });

    }
};
