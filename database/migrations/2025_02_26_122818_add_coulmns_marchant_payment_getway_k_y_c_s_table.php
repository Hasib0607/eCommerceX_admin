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
        Schema::table('marchant_payment_getway_k_y_c_s', function (Blueprint $table) {
            $table->enum('payment_gatway', ["bkash", "nagad", "rocket", "amarpay", "paypal", "stripe", "ssl"])->after('online_bank')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marchant_payment_getway_k_y_c_s', function (Blueprint $table) {
            if (Schema::hasColumn('marchant_payment_getway_k_y_c_s', 'payment_gatway')) {
                $table->dropColumn('payment_gatway');
            }
        });
    }
};
