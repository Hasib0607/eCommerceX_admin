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
        Schema::create('chatbot_question_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId("question_id")->constrained("chatbot_questions")->onDelete('cascade');
            $table->foreignId("answer_id")->constrained("chatbot_answers")->onDelete('cascade');
            $table->unsignedBigInteger("group_id");
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
        Schema::dropIfExists('chatbot_question_answers');
    }
};
