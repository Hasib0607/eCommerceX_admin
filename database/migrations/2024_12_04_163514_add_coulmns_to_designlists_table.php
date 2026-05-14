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
            $table->string('button_bg_color')->after('button_color')->nullable();
            $table->string('button1')->after('button_color')->nullable();
            $table->string('button1_color')->after('button1')->nullable();
            $table->string('button1_bg_color')->after('button1_color')->nullable();
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
            if (Schema::hasColumn('designlists', 'button_bg_color')) {
                $table->dropColumn('button_bg_color');
            }
            if (Schema::hasColumn('designlists', 'button1')) {
                $table->dropColumn('button1');
            }
            if (Schema::hasColumn('designlists', 'button1_color')) {
                $table->dropColumn('button1_color');
            }
            if (Schema::hasColumn('designlists', 'button1_bg_color')) {
                $table->dropColumn('button1_bg_color');
            }
        });
    }
};
