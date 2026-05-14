<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }

            if (!Schema::hasColumn('products', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_keywords');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'short_description')) {
                $table->dropColumn('short_description');
            }

            if (Schema::hasColumn('products', 'seo_description')) {
                $table->dropColumn('seo_description');
            }
        });
    }
};

