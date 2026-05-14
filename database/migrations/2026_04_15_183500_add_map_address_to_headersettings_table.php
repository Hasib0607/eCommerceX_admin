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
        if (!Schema::hasColumn('headersettings', 'map_address')) {
            Schema::table('headersettings', function (Blueprint $table) {
                $table->longText('map_address')->nullable()->after('address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('headersettings', 'map_address')) {
            Schema::table('headersettings', function (Blueprint $table) {
                $table->dropColumn('map_address');
            });
        }
    }
};
