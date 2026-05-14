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
        Schema::table('store_designs', function (Blueprint $table) {
            $table->string('link')->after('button_bg_color')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_designs', function (Blueprint $table) {
            if (Schema::hasColumn('store_designs', 'link')) {
                $table->dropColumn('link');
            }
        });
    }
};
