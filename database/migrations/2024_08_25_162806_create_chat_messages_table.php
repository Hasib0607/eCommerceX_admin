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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations', 'id')->cascadeOnDelete();
            $table->enum('sender_type', ['visitor', 'agent', 'bot']);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->longText('content')->nullable();
            $table->longText('file_url')->nullable();
            $table->enum('message_type', ['text', 'file', 'mix'])->default('text');
            $table->enum('file_type', ['image', 'pdf', 'other'])->default('other');
            $table->tinyInteger('seen_status')->default(0)->comment("0=unseen|1=seen");
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
        Schema::dropIfExists('chat_messsages');
    }
};
