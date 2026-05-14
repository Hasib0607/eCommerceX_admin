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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('chat_visitors', 'id')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores', 'id')->nullOnDelete();
            $table->enum('sender_type', ['visitor', 'agent', 'bot']);
            $table->enum('status', ['open', 'closed', 'pending'])->default('open');
            $table->tinyInteger("type")->default(0)->comment("0=Sales|1=Tech");
            $table->tinyInteger('lang')->default('0')->comment("0=English|1=Bangla");
            $table->longText('last_message')->nullable();
            $table->tinyInteger('seen_status')->default(0)->comment("0=unseen|1=seen");
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chat_conversations');
    }
};
