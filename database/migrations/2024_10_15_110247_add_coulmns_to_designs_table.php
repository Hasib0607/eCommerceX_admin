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
            $table->string('blog')->after('offer')->nullable();
            $table->string('contact')->after('blog')->nullable();
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
            if (Schema::hasColumn('designs', 'blog')) {
                $table->dropColumn('blog');
            }
            if (Schema::hasColumn('designs', 'contact')) {
                $table->dropColumn('contact');
            }
        });
    }
};
