<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_campaign_batches', function (Blueprint $table) {
            $table->string('status')->default('active')->after('scheduled_for');
            $table->unsignedInteger('total_runs')->default(0)->after('status');
            $table->unsignedInteger('total_recipients')->default(0)->after('total_runs');
            $table->unsignedInteger('total_sent_count')->default(0)->after('total_recipients');
            $table->unsignedInteger('total_failed_count')->default(0)->after('total_sent_count');
            $table->timestamp('archived_at')->nullable()->after('last_run_failed_count');
        });

        Schema::create('renewal_campaign_batch_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('batch_item_id')->nullable()->index();
            $table->unsignedBigInteger('outbound_id')->nullable()->index();
            $table->string('session_id')->nullable();
            $table->string('status')->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_campaign_batch_dispatches');

        Schema::table('renewal_campaign_batches', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'total_runs',
                'total_recipients',
                'total_sent_count',
                'total_failed_count',
                'archived_at',
            ]);
        });
    }
};
