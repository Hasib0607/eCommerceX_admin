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
            $table->string('stock_status')->after('unit')->nullable();
            $table->longText('pre_order_note')->after('stock_status')->nullable();
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
            if (Schema::hasColumn('products', 'stock_status')) {
                $table->dropColumn('stock_status');
            }
            if (Schema::hasColumn('products', 'pre_order_note')) {
                $table->dropColumn('pre_order_note');
            }
        });
    }
};
