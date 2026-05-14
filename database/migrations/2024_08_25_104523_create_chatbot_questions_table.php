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
        Schema::create('chatbot_questions', function (Blueprint $table) {
            $table->id();
            $table->longText("question");
            $table->tinyInteger("type")->default(0)->comment("0=Sales|1=Tech");
            $table->tinyInteger('lang')->default('0')->comment("0=English|1=Bangla");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chatbot_questions');
    }
};
