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
            $table->string('uddoktapay')->after('amarpay')->nullable();
            $table->string('uddoktapay_text')->after('amarpay_text')->default("Uddokta Pay");
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
            if (Schema::hasColumn('headersettings', 'uddoktapay')) {
                $table->dropColumn('uddoktapay');
            }
            if (Schema::hasColumn('headersettings', 'uddoktapay_text')) {
                $table->dropColumn('uddoktapay_text');
            }
        });
    }
};
