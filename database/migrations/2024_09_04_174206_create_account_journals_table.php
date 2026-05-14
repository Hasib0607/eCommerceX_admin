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
        Schema::create('account_journals', function (Blueprint $table) {
            $table->id();
            $table->string("voucher")->nullable();
            $table->foreignId("user_id")->constrained("users")->onDelete('cascade');
            $table->foreignId("store_id")->constrained("stores")->onDelete('cascade');
            $table->foreignId("order_id")->nullable()->constrained("orders")->onDelete('cascade');
            $table->decimal("product_order_amount")->default(0.00);
            $table->decimal("commission_percent")->default(0.00);
            $table->decimal("payment_amount")->nullable();
            $table->string("payment_method")->nullable();
            $table->string("payment_number")->nullable();
            $table->string("transaction_id")->nullable();
            $table->integer("currency_id")->default(1);
            $table->decimal("currency_commission_amount")->default(0.00);
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
        Schema::dropIfExists('account_journals');
    }


};
