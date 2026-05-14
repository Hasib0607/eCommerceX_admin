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
        Schema::table('posplans', function (Blueprint $table) {
            $table->double('usd_price')->after('price')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posplans', function (Blueprint $table) {
            if (Schema::hasColumn('posplans', 'usd_price')) {
                $table->dropColumn('usd_price');
            }
        });
    }
};
