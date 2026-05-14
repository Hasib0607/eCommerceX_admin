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
            $table->string('amarpay')->after('nagad')->nullable();
            $table->string('amarpay_text')->after('ap_text')->default("Amar Pay");
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
            if (Schema::hasColumn('headersettings', 'amarpay')) {
                $table->dropColumn('amarpay');
            }
            if (Schema::hasColumn('headersettings', 'amarpay_text')) {
                $table->dropColumn('amarpay_text');
            }
        });
    }
};
