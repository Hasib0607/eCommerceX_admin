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
        Schema::table('chatbot_questions', function (Blueprint $table) {
            $table->tinyInteger('type_both')->after('type')->default(0)->comment("0=Inactive|1=Active");
            $table->tinyInteger('lang_both')->after('lang')->default(0)->comment("0=Inactive|1=Active");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('chatbot_questions', function (Blueprint $table) {
            if (Schema::hasColumn('chatbot_questions', 'type_both')) {
                $table->dropColumn('type_both');
            }
            if (Schema::hasColumn('chatbot_questions', 'lang_both')) {
                $table->dropColumn('lang_both');
            }
        });
    }
};
