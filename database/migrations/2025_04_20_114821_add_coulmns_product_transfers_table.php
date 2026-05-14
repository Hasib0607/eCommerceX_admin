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
        Schema::table('product_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->after('transfer_qty')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('product_transfers', 'store_id')) {
                $table->dropColumn('store_id');
            }
        });
    }
};
