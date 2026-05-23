<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans') || Schema::hasColumn('plans', 'is_trial')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $column = $table->boolean('is_trial')->default(false);
            if (Schema::hasColumn('plans', 'status')) {
                $column->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('plans') || !Schema::hasColumn('plans', 'is_trial')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_trial');
        });
    }
};
