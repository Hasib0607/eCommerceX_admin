<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 30)->nullable();
            }
            if (!Schema::hasColumn('users', 'age')) {
                $table->string('age', 10)->nullable();
            }
            if (!Schema::hasColumn('users', 'verification_type')) {
                $table->string('verification_type', 50)->nullable();
            }
            if (!Schema::hasColumn('users', 'identification_number')) {
                $table->string('identification_number', 100)->nullable();
            }
            if (!Schema::hasColumn('users', 'voter_id_image')) {
                $table->text('voter_id_image')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach (['gender', 'age', 'verification_type', 'identification_number', 'voter_id_image'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
