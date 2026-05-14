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
        Schema::create('website_setup_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('model_no')->nullable();
            $table->string('product_name')->nullable();
            $table->longText('description')->nullable();
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('price')->nullable();
            $table->string('brand')->nullable();
            $table->string('supplier')->nullable();
            $table->string('cost')->nullable();
            $table->string('quantity')->nullable();
            $table->string('discount')->nullable();
            $table->string('discount_type')->nullable();
            $table->string('color')->nullable();
            $table->string('color_code')->nullable();
            $table->string('size')->nullable();
            $table->string('unit')->nullable();
            $table->longText('other_info')->nullable();
            $table->tinyInteger('save_status')->default(0)->comment('0=Not Save|1=Save');
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
        Schema::dropIfExists('website_setup_products');
    }
};
