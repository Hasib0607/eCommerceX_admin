<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('usd_price', 8, 2)->after('twentyfourdis')->nullable();
            $table->string('usd_discount_type', 50)->after('usd_price')->nullable();
            $table->decimal('usd_1_dis', 8, 2)->after('usd_discount_type')->nullable();
            $table->decimal('usd_6_dis', 8, 2)->after('usd_1_dis')->nullable();
            $table->decimal('usd_12_dis', 8, 2)->after('usd_6_dis')->nullable();
            $table->decimal('usd_24_dis', 8, 2)->after('usd_12_dis')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'usd_price')) {
                $table->dropColumn('usd_price');
                $table->dropColumn('usd_discount_type');
                $table->dropColumn('usd_1_dis');
                $table->dropColumn('usd_6_dis');
                $table->dropColumn('usd_12_dis');
                $table->dropColumn('usd_24_dis');
            }
        });
    }
};
