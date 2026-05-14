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
            $table->string('bg_image')->after('link')->nullable();
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
            if (Schema::hasColumn('store_designs', 'bg_image')) {
                $table->dropColumn('bg_image');
            }
        });
    }
};
