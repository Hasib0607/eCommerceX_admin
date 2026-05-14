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
            $table->index('phone');
            $table->index('name');
            $table->index('email');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('name');
            $table->index('url');
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
            $table->dropIndex(['phone']);
            $table->dropIndex(['name']);
            $table->dropIndex(['email']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['name']);
            $table->dropIndex(['url']);
        });
    }
};
