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
        Schema::table('designs', function (Blueprint $table) {
            $table->string('announcement')->after('youtube')->nullable()->default("null");
            $table->string('about')->after('announcement')->nullable()->default("null");
            $table->string('newsletter')->after('about')->nullable()->default("null");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('designs', function (Blueprint $table) {
            if (Schema::hasColumn('designs', 'announcement')) {
                $table->dropColumn('announcement');
            }
            if (Schema::hasColumn('designs', 'about')) {
                $table->dropColumn('about');
            }
            if (Schema::hasColumn('designs', 'newsletter')) {
                $table->dropColumn('newsletter');
            }
        });
    }
};
