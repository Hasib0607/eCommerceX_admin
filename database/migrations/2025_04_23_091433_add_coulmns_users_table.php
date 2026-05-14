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
        Schema::table('users', function (Blueprint $table) {
            $table->string('register_from')->after('total_commission')->nullable();
            $table->tinyInteger('paid_registration')->after('register_from')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'register_from')) {
                $table->dropColumn('register_from');
            }
            if (Schema::hasColumn('users', 'paid_registration')) {
                $table->dropColumn('paid_registration');
            }
        });
    }
};
