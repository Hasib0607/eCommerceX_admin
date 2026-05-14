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
            $table->string('cod_text')->after('order_sms')->default("Cash On Delivery");
            $table->string('bkash_text')->after('cod_text')->default("bKash Payment");
            $table->string('ap_text')->after('bkash_text')->default("Advance Payment");
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
            if (Schema::hasColumn('headersettings', 'cod_text')) {
                $table->dropColumn('cod_text');
            }
            if (Schema::hasColumn('headersettings', 'bkash_text')) {
                $table->dropColumn('bkash_text');
            }
            if (Schema::hasColumn('headersettings', 'ap_text')) {
                $table->dropColumn('ap_text');
            }
        });
    }
};
