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
        Schema::create('merchant_account_journals', function (Blueprint $table) {
            $table->id();
            $table->string("voucher")->nullable();
            $table->foreignId("user_id")->constrained("users")->onDelete('cascade');
            $table->foreignId("store_id")->constrained("stores")->onDelete('cascade');
            $table->foreignId("order_id")->nullable()->constrained("orders")->onDelete('cascade');
            $table->string("transaction_id")->nullable();
            $table->string("order_transaction_id")->nullable();
            $table->decimal("order_amount")->default(0.00);
            $table->decimal("commission_percent")->default(0.00);
            $table->decimal("commission_amount")->default(0.00);
            $table->decimal("store_amount")->default(0.00);
            $table->string("note")->nullable();
            $table->decimal("dr")->default(0.00);
            $table->decimal("cr")->default(0.00);
            $table->decimal("balance")->default(0.00);
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
        Schema::dropIfExists('merchant_account_journals');
    }
};
