<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('addons_orders', function (Blueprint $table) {
            $table->decimal('late_fee', 10, 2)->default(0)->after('total');
            $table->string('late_fee_reason')->nullable()->after('late_fee');
            $table->unsignedInteger('late_fee_overdue_days')->default(0)->after('late_fee_reason');
            $table->string('currency_type', 10)->nullable()->after('late_fee_overdue_days'); // 'BDT' or 'USD'
        });
    }

    public function down(): void
    {
        Schema::table('addons_orders', function (Blueprint $table) {
            $table->dropColumn([
                'late_fee',
                'late_fee_reason',
                'late_fee_overdue_days',
                'currency_type',
            ]);
        });
    }
};