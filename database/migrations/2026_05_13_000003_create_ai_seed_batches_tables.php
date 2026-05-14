<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_seed_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('mode', 20)->default('auto')->index();
            $table->unsignedBigInteger('business_category_id')->nullable()->index();
            $table->string('image_ratio', 20)->nullable();
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->json('blueprint')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_seed_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('source_image_id')->nullable()->index();
            $table->string('generated_image_path')->nullable();
            $table->boolean('is_demo')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_seed_products');
        Schema::dropIfExists('ai_seed_batches');
    }
};
