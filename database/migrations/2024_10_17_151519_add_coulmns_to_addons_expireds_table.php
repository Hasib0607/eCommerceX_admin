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
        Schema::table('addons_expireds', function (Blueprint $table) {
            $table->bigInteger('pos_plan_id')->after('addons_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('addons_expireds', function (Blueprint $table) {
            if (Schema::hasColumn('addons_expireds', 'pos_plan_id')) {
                $table->dropColumn('pos_plan_id');
            }
        });
    }
};
