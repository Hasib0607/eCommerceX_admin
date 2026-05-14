<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sliders', function (Blueprint $table) {
            $table->string('subtitle_color', 20)->after('color')->nullable();
            $table->string('button', 50)->after('subtitle')->nullable();
            $table->string('button_color', 20)->after('subtitle_color')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sliders', function (Blueprint $table) {
            if (Schema::hasColumn('sliders', 'subtitle_color')) {
                $table->dropColumn('subtitle_color');
            }
            if (Schema::hasColumn('sliders', 'button')) {
                $table->dropColumn('button');
            }
            if (Schema::hasColumn('sliders', 'button_color')) {
                $table->dropColumn('button_color');
            }
        });
    }
};
