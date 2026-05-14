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
        Schema::table('websitesetups', function (Blueprint $table) {
            $table->tinyInteger('data_submit')->after('status')->default(0)->comment("0=Data not submit, 1=Data submit");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('websitesetups', function (Blueprint $table) {
            if (Schema::hasColumn('websitesetups', 'data_submit')) {
                $table->dropColumn('data_submit');
            }
        });
    }
};
