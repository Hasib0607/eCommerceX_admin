<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons_order_payment_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('addons_order_id');
            $table->decimal('payment_amount', 10, 2)->default(0);
            $table->decimal('previous_paid_amount', 10, 2)->default(0);
            $table->decimal('previous_due_amount', 10, 2)->default(0);
            $table->decimal('current_paid_amount', 10, 2)->default(0);
            $table->decimal('current_due_amount', 10, 2)->default(0);
            $table->string('due_amount_status')->default('paid');
            $table->string('payment_method')->nullable();
            $table->string('payment_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('addons_order_id')
                ->references('id')
                ->on('addons_orders')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons_order_payment_histories');
    }
};
