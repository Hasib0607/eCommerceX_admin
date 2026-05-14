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
        Schema::table('admin_blogs', function (Blueprint $table) {
            $table->tinyInteger('website')->after('permalink')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_blogs', function (Blueprint $table) {
            if (Schema::hasColumn('admin_blogs', 'website')) {
                $table->dropColumn('website');
            }
        });
    }
};
