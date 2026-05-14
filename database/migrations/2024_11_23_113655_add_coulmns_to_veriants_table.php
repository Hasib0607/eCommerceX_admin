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
        Schema::table('veriants', function (Blueprint $table) {
            $table->string('color_image')->after('image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('veriants', function (Blueprint $table) {
            if (Schema::hasColumn('veriants', 'color_image')) {
                $table->dropColumn('color_image');
            }
        });
    }
};
