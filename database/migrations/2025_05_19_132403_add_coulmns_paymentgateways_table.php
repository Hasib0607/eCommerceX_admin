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
        Schema::table('paymentgateways', function (Blueprint $table) {
            $table->string('merchant_id')->after('api_password')->nullable();
            $table->string('merchant_number')->after('merchant_id')->nullable();
            $table->longText('public_key')->after('merchant_number')->nullable();
            $table->longText('private_key')->after('public_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('paymentgateways', function (Blueprint $table) {
            if (Schema::hasColumn('paymentgateways', 'merchant_id')) {
                $table->dropColumn('merchant_id');
            }
            if (Schema::hasColumn('paymentgateways', 'merchant_number')) {
                $table->dropColumn('merchant_number');
            }
            if (Schema::hasColumn('paymentgateways', 'public_key')) {
                $table->dropColumn('public_key');
            }
            if (Schema::hasColumn('paymentgateways', 'private_key')) {
                $table->dropColumn('private_key');
            }
        });
    }
};
