<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('saas_features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name', 190);
            $table->enum('type', ['page', 'action', 'quota'])->default('action');
            $table->boolean('enabled_by_default')->default(true);
            $table->integer('default_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('feature_key', 120);
            $table->boolean('is_enabled')->default(true);
            $table->integer('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
            $table->index(['feature_key']);
        });

        Schema::create('store_entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('feature_key', 120);
            $table->boolean('is_enabled')->nullable();
            $table->integer('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'feature_key']);
            $table->index(['feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_entitlement_overrides');
        Schema::dropIfExists('plan_entitlements');
        Schema::dropIfExists('saas_features');
    }
};

