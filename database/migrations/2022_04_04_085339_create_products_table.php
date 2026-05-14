<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable;
            $table->text('description');
            $table->decimal('regular_price')->nullable();
            $table->string('discount_type')->nullable();
            $table->decimal('promotional_price')->nullable();
            $table->string('tax_type')->nullable();
            $table->decimal('tax_rate')->nullable();
            $table->string('quantity')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('weight')->nullable();
            $table->decimal('shipping_fee')->nullable();
            $table->text('images')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('tags')->nullable();
            $table->string('status')->nullable();
            $table->string('creator')->nullable();
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
        Schema::dropIfExists('products');
    }
};
