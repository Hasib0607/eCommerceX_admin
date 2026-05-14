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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('currency')->after('template_id')->nullable();
            $table->string('currency_rate')->after('currency')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('stores', 'currency_rate')) {
                $table->dropColumn('currency_rate');
            }
        });
    }
};
