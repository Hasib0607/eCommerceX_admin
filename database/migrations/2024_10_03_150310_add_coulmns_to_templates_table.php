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
        Schema::table('templates', function (Blueprint $table) {
            $table->string('blog')->after('offer')->nullable();
            $table->string('contact')->after('blog')->nullable();
            $table->string('announcement')->after('contact')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('templates', function (Blueprint $table) {
            if (Schema::hasColumn('templates', 'blog')) {
                $table->dropColumn('blog');
            }
            if (Schema::hasColumn('templates', 'contact')) {
                $table->dropColumn('contact');
            }
            if (Schema::hasColumn('templates', 'announcement')) {
                $table->dropColumn('announcement');
            }
        });
    }
};
