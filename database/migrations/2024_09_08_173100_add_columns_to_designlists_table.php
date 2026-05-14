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
            $table->string('title', 250)->after('value')->nullable();
            $table->string('title_color', 250)->after('title')->nullable();
            $table->string('button', 250)->after('title_color')->nullable();
            $table->string('image_description', 250)->after('button')->nullable();
            $table->string('subtitle', 250)->after('image_description')->nullable();
            $table->string('subtitle_color', 20)->after('subtitle')->nullable();
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
        Schema::table('designlists', function (Blueprint $table) {
            if (Schema::hasColumn('designlists', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('designlists', 'title_color')) {
                $table->dropColumn('title_color');
            }
            if (Schema::hasColumn('designlists', 'button')) {
                $table->dropColumn('button');
            }
            if (Schema::hasColumn('designlists', 'image_description')) {
                $table->dropColumn('image_description');
            }
            if (Schema::hasColumn('designlists', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
            if (Schema::hasColumn('designlists', 'subtitle_color')) {
                $table->dropColumn('subtitle_color');
            }
            if (Schema::hasColumn('designlists', 'button_color')) {
                $table->dropColumn('button_color');
            }
        });
    }
};
