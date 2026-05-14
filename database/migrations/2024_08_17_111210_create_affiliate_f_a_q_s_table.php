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
        Schema::create('affiliate_f_a_q_s', function (Blueprint $table) {
            $table->id();
            $table->longText('question');
            $table->longText('answer')->nullable();
            $table->string('video_link')->nullable();
            $table->tinyInteger('status',)->default(0)->comment('0=Inactive|1=Active');
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
        Schema::dropIfExists('affiliate_f_a_q_s');
    }
};
