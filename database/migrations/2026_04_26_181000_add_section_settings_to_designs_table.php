<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('designs', 'section_settings')) {
            Schema::table('designs', function (Blueprint $table): void {
                $table->longText('section_settings')->nullable()->after('text_color');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('designs', 'section_settings')) {
            Schema::table('designs', function (Blueprint $table): void {
                $table->dropColumn('section_settings');
            });
        }
    }
};
