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
        Schema::create('scraped_products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->text('keywords')->nullable();
            $table->text('url');
            $table->text('image');
            $table->decimal('original_price', 10, 2)->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('৳');
            $table->string('in_stock', 4)->nullable();
            $table->string('product_id')->nullable();
            $table->string('sku_id')->nullable();
            $table->string('source_site');
            $table->text('source_url');
            $table->string('brand_name')->nullable();
            $table->string('seller_name')->nullable();
            $table->string('location')->nullable();

            // Indexes for better performance
            $table->index('product_id');
            $table->index('sku_id');
            $table->index('source_site');
            $table->index('brand_name');

            $table->timestamp('last_verified_at')->nullable(); // Used for TTL logic
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('scraped_products');
    }

};

