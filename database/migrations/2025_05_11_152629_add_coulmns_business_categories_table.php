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
        Schema::table('business_categories', function (Blueprint $table) {
            $table->string('parent_id')->after('slug')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('business_categories', function (Blueprint $table) {
            if (Schema::hasColumn('business_categories', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};
