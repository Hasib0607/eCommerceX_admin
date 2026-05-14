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
            $table->tinyInteger('isDomainDelete')->after('pay_mail_status')->default(0)->comment("0=Not Delete|1=Delete");
            $table->tinyInteger('isCFileDelete')->after('isDomainDelete')->default(0)->comment("0=Not Delete|1=Delete");
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
            if (Schema::hasColumn('stores', 'isDomainDelete')) {
                $table->dropColumn('isDomainDelete');
            }
            if (Schema::hasColumn('stores', 'isCFileDelete')) {
                $table->dropColumn('isCFileDelete');
            }
        });
    }
};
