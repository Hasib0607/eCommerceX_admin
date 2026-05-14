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
        Schema::table('moduluses', function (Blueprint $table) {
            $table->tinyInteger('modulus_type')->after('type')->default(0)->comment("0=Addon|1=Marketing");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('moduluses', function (Blueprint $table) {
            if (Schema::hasColumn('moduluses', 'modulus_type')) {
                $table->dropColumn('modulus_type');
            }
        });
    }
};
