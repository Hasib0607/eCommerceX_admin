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
        Schema::table('modulus_payments', function (Blueprint $table) {
            $table->string('total_product')->after('transaction_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('modulus_payments', function (Blueprint $table) {
            if (Schema::hasColumn('modulus_payments', 'total_product')) {
                $table->dropColumn('total_product');
            }
        });
    }
};
