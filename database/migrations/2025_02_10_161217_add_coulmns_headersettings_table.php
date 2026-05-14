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
            $table->tinyInteger('button_status')->after('amarpay_text')->default(0);
            $table->tinyInteger('rtl_status')->after('button_status')->default(0);
            $table->tinyInteger('theme_lock')->after('rtl_status')->default(0);
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
            if (Schema::hasColumn('headersettings', 'button_status')) {
                $table->dropColumn('button_status');
            }
            if (Schema::hasColumn('headersettings', 'rtl_status')) {
                $table->dropColumn('rtl_status');
            }
            if (Schema::hasColumn('headersettings', 'theme_lock')) {
                $table->dropColumn('theme_lock');
            }
        });
    }
};
