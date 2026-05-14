<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('headersettings', 'tiktok_link')) {
            Schema::table('headersettings', function (Blueprint $table): void {
                $table->string('tiktok_link', 1000)->nullable()->after('twitter_link');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('headersettings', 'tiktok_link')) {
            Schema::table('headersettings', function (Blueprint $table): void {
                $table->dropColumn('tiktok_link');
            });
        }
    }
};
