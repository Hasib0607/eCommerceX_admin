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
        Schema::table('quick_logins', function (Blueprint $table) {
            $table->string('general_access_token')->after('facebook_pixel')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quick_logins', function (Blueprint $table) {
            if (Schema::hasColumn('quick_logins', 'general_access_token')) {
                $table->dropColumn('general_access_token');
            }
        });
    }
};
