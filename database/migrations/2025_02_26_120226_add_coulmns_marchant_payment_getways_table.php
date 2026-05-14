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
        Schema::table('marchant_payment_getways', function (Blueprint $table) {
            $table->enum('payment_gatway', ["bkash", "nagad", "rocket", "amarpay", "paypal", "stripe", "ssl"])->after('store_id')->nullable();
            $table->tinyInteger('status')->after('payment_gatway')->default(0)->comment("1=Active|0=Inactive");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('marchant_payment_getways', function (Blueprint $table) {
            if (Schema::hasColumn('marchant_payment_getways', 'payment_gatway')) {
                $table->dropColumn('payment_gatway');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'status')) {
                $table->dropColumn('status');
            }

        });
    }
};
