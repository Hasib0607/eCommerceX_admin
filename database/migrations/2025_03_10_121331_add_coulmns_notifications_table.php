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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->after('id')->nullable();
            $table->string('body')->after('title')->nullable();
            $table->string('type')->after('body')->nullable();
            $table->string('user_type')->after('type')->nullable();
            $table->string('user_id')->after('user_type')->nullable();
            $table->string('store_id')->after('user_id')->nullable();
            $table->string('conversation_id')->after('store_id')->nullable();
            $table->string('link')->after('store_id')->nullable();
            $table->tinyInteger('view')->after('link')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('notifications', 'body')) {
                $table->dropColumn('body');
            }
            if (Schema::hasColumn('notifications', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('notifications', 'user_type')) {
                $table->dropColumn('user_type');
            }
            if (Schema::hasColumn('notifications', 'user_id')) {
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('notifications', 'store_id')) {
                $table->dropColumn('store_id');
            }
            if (Schema::hasColumn('notifications', 'link')) {
                $table->dropColumn('link');
            }
            if (Schema::hasColumn('notifications', 'view')) {
                $table->dropColumn('view');
            }
        });
    }
};
