<?php

use App\Models\AiSeedImageLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_seed_image_libraries')) {
            return;
        }

        Schema::table('ai_seed_image_libraries', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
                $table->json('business_category_ids')->nullable()->after('business_category_id');
            }
        });

        AiSeedImageLibrary::query()
            ->whereNotNull('business_category_id')
            ->whereNull('business_category_ids')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $row->business_category_ids = [(int) $row->business_category_id];
                    $row->save();
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ai_seed_image_libraries') || !Schema::hasColumn('ai_seed_image_libraries', 'business_category_ids')) {
            return;
        }

        Schema::table('ai_seed_image_libraries', function (Blueprint $table) {
            $table->dropColumn('business_category_ids');
        });
    }
};
