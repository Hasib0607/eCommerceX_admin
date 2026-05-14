<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_affiliate_withdraw_requests', function (Blueprint $table) {
            $table->longText('comment')->after('status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_affiliate_withdraw_requests', function (Blueprint $table) {
            if (Schema::hasColumn('product_affiliate_withdraw_requests', 'comment')) {
                $table->dropColumn('comment');
            }
        });
    }
};
