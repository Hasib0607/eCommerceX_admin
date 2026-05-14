<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_seed_image_libraries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_category_id')->nullable()->index();
            $table->string('business_category_name')->nullable();
            $table->string('category_slug')->nullable()->index();
            $table->string('subcategory_slug')->nullable()->index();
            $table->string('usage_type', 30)->default('product')->index();
            $table->string('ratio_key', 20)->nullable()->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('tags')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->timestamps();

            $table->index(['usage_type', 'business_category_id', 'status'], 'ai_seed_img_usage_category_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_seed_image_libraries');
    }
};
