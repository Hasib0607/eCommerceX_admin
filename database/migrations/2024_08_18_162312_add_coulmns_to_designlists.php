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
        Schema::table('designlists', function (Blueprint $table) {
            $table->string('title')->nullable()->after('value');
            $table->string('title_color')->nullable()->after('title');
            $table->string('title_bg')->nullable()->after('title_color');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('designlists', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->dropColumn('title_color');
            $table->dropColumn('title_bg');
        });
    }
};
