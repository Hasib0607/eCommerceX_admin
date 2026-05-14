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
        Schema::table('headersettings', function (Blueprint $table) {
            if (!Schema::hasColumn('headersettings', 'pinterest_link')) {
                $table->string('pinterest_link', 1000)->nullable()->after('lined_in_link');
            }
            if (!Schema::hasColumn('headersettings', 'twitter_link')) {
                $table->string('twitter_link', 1000)->nullable()->after('pinterest_link');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('headersettings', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('headersettings', 'twitter_link')) {
                $drops[] = 'twitter_link';
            }
            if (Schema::hasColumn('headersettings', 'pinterest_link')) {
                $drops[] = 'pinterest_link';
            }
            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
