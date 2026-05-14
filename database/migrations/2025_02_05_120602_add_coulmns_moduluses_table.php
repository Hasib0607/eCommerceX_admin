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
            $table->decimal('price_usd')->after('price')->default(0.00);
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
            if (Schema::hasColumn('moduluses', 'price_usd')) {
                $table->dropColumn('price_usd');
            }
        });
    }
};
