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
            $table->tinyInteger('position')->after('status')->default(0);
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
            if (Schema::hasColumn('moduluses', 'position')) {
                $table->dropColumn('position');
            }
        });
    }
};
