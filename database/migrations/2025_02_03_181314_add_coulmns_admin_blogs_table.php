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
            $table->longText('canonical_url')->after('permalink')->nullable();
            $table->longText('custom_script')->after('canonical_url')->nullable();
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
            if (Schema::hasColumn('admin_blogs', 'canonical_url')) {
                $table->dropColumn('canonical_url');
            }
            if (Schema::hasColumn('admin_blogs', 'custom_script')) {
                $table->dropColumn('custom_script');
            }
        });
    }
};
