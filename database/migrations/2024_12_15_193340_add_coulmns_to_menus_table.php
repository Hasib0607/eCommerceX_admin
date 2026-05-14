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
        Schema::table('menus', function (Blueprint $table) {
            $table->string('custom_link')->after('sort')->nullable();
            $table->string('status')->after('custom_link')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'custom_link')) {
                $table->dropColumn('custom_link');
            }
            if (Schema::hasColumn('menus', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
