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
            $table->string('amarpay_min_withdraw')->after('stripe')->nullable()->default(1000);
            $table->string('amarpay_max_withdraw')->after('amarpay_min_withdraw')->nullable();
            $table->string('ssl_min_withdraw')->after('amarpay_max_withdraw')->nullable()->default(1000);
            $table->string('ssl_max_withdraw')->after('ssl_min_withdraw')->nullable();
            $table->string('paypal_min_withdraw')->after('ssl_max_withdraw')->nullable()->default(20);
            $table->string('paypal_max_withdraw')->after('paypal_min_withdraw')->nullable();
            $table->string('stripe_min_withdraw')->after('paypal_max_withdraw')->nullable()->default(20);
            $table->string('stripe_max_withdraw')->after('stripe_min_withdraw')->nullable();
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
            if (Schema::hasColumn('marchant_payment_getways', 'amarpay_min_withdraw')) {
                $table->dropColumn('amarpay_min_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'amarpay_max_withdraw')) {
                $table->dropColumn('amarpay_max_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'ssl_min_withdraw')) {
                $table->dropColumn('ssl_min_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'ssl_max_withdraw')) {
                $table->dropColumn('ssl_max_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'paypal_min_withdraw')) {
                $table->dropColumn('paypal_min_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'paypal_max_withdraw')) {
                $table->dropColumn('paypal_max_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'stripe_min_withdraw')) {
                $table->dropColumn('stripe_min_withdraw');
            }
            if (Schema::hasColumn('marchant_payment_getways', 'stripe_max_withdraw')) {
                $table->dropColumn('stripe_max_withdraw');
            }

        });
    }
};
