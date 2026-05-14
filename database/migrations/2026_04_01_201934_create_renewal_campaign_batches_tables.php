<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_campaign_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cohort_key');
            $table->string('bot_type')->default('sales');
            $table->text('message_text');
            $table->string('image_url')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_run_success_count')->default(0);
            $table->unsignedInteger('last_run_failed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('renewal_campaign_batch_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->string('cohort_key');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('display_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('store_name')->nullable();
            $table->string('store_url')->nullable();
            $table->string('status_label')->nullable();
            $table->date('registration_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_campaign_batch_items');
        Schema::dropIfExists('renewal_campaign_batches');
    }
};
