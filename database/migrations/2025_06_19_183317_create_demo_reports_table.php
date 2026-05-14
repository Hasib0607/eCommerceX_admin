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
        Schema::create('demo_reports', function (Blueprint $table) {
            $table->id();
            $table->string("total_admin")->nullable()->default(0);
            $table->string("total_paid_store")->nullable()->default(0);

            $table->string("total_dropshipper")->nullable()->default(0);
            $table->string("total_paid_dropshipper")->nullable()->default(0);

            $table->string("total_customer")->nullable()->default(0);
            $table->string("total_affiliate")->nullable()->default(0);
            $table->string("total_customer_affiliate")->nullable()->default(0);

            $table->string("lifetime_total_paid_store")->nullable()->default(0);
            $table->string("lifetime_total_paid_dropshipper")->nullable()->default(0);

            $table->string("total_new_sell_amount_monthly")->nullable()->default(0);
            $table->string("total_new_sell_amount_yearly")->nullable()->default(0);

            $table->string("total_renew_sell_amount_monthly")->nullable()->default(0);
            $table->string("total_renew_sell_amount_yearly")->nullable()->default(0);

            $table->string("total_addon_amount_monthly")->nullable()->default(0);
            $table->string("total_addon_amount_yearly")->nullable()->default(0);

            $table->string("total_addon_amount_without_domain_monthly")->nullable()->default(0);
            $table->string("total_addon_amount_without_domain_yearly")->nullable()->default(0);

            $table->string("total_package_amount_monthly")->nullable()->default(0);
            $table->string("total_package_amount_yearly")->nullable()->default(0);

            $table->string("total_revenue_monthly")->nullable()->default(0);
            $table->string("total_revenue_yearly")->nullable()->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('demo_reports');
    }
};
