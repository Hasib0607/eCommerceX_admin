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
        Schema::table('headersettings', function (Blueprint $table) {
            $table->string('paypal')->after('nagad')->nullable();
            $table->string('stripe')->after('paypal')->nullable();

            $table->string('nagad_text')->after('bkash_text')->default("Nagad");
            $table->string('paypal_text')->after('nagad_text')->default("Paypal");
            $table->string('stripe_text')->after('paypal_text')->default("Stripe");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('headersettings', function (Blueprint $table) {
            if (Schema::hasColumn('headersettings', 'paypal')) {
                $table->dropColumn('paypal');
            }
            if (Schema::hasColumn('headersettings', 'stripe')) {
                $table->dropColumn('stripe');
            }
            if (Schema::hasColumn('headersettings', 'nagad_text')) {
                $table->dropColumn('nagad_text');
            }
            if (Schema::hasColumn('headersettings', 'paypal_text')) {
                $table->dropColumn('paypal_text');
            }
            if (Schema::hasColumn('headersettings', 'stripe_text')) {
                $table->dropColumn('stripe_text');
            }
        });
    }
};
