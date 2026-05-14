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
        Schema::create('affiliate_questions', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->string('question_type')->comment('checkbox|radio|plain');
            $table->string('answer_option_one')->nullable();
            $table->string('answer_option_two')->nullable();
            $table->string('answer_option_three')->nullable();
            $table->string('answer_option_four')->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=Inactive|1=Active');
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
        Schema::dropIfExists('affiliate_questions');
    }
};
