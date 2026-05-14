<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            return;
        }

        Schema::create('stores', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name')->nullable()->index();
            $table->string('slug')->nullable();
            $table->string('url')->nullable()->index();
            $table->string('type')->nullable();
            $table->string('category_id', 191)->nullable();
            $table->string('purpose')->nullable();
            $table->string('user_id')->nullable()->index();
            $table->string('customer_id')->nullable();
            $table->string('status')->nullable();
            $table->boolean('store_status')->default(1)->comment('0=Inactive|1=Active');
            $table->tinyInteger('paid_registration')->default(0);
            $table->string('plan_id')->nullable();
            $table->string('template_id')->nullable();
            $table->integer('currency')->default(1);
            $table->decimal('currency_rate', 10, 4)->default(117.5600);
            $table->string('bkash')->default('no');
            $table->date('purchase_date')->nullable();
            $table->date('renew_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('dropship_commission')->default(3);
            $table->tinyInteger('order_pull')->default(0)->comment('0=order place|1=order delivered');
            $table->decimal('overflow_commission')->default(10000);
            $table->string('pos_plan_id')->nullable();
            $table->date('pos_plan_start_date')->nullable();
            $table->date('pos_plan_expiry_date')->nullable();
            $table->bigInteger('pos_plan_month')->default(0)->nullable();
            $table->string('pos_plan_status')->nullable();
            $table->string('digital_plan_id')->nullable();
            $table->date('digital_plan_start_date')->nullable();
            $table->date('digital_plan_end_date')->nullable();
            $table->string('digital_plan_status')->nullable();
            $table->string('plan_status')->nullable();
            $table->integer('trail')->default(0);
            $table->bigInteger('month')->nullable();
            $table->string('upcoming_plan_id')->nullable();
            $table->string('upcoming_plan_month')->nullable();
            $table->timestamp('upcoming_plan_purchase_date')->nullable();
            $table->timestamp('upcoming_plan_expiry_date')->nullable();
            $table->string('upcoming_pos_plan_id')->nullable();
            $table->string('upcoming_pos_plan_month')->nullable();
            $table->date('upcoming_pos_plan_start_date')->nullable();
            $table->date('upcoming_pos_plan_expiry_date')->nullable();
            $table->string('upcoming_digital_plan_id')->nullable();
            $table->string('upcoming_digital_plan_month')->nullable();
            $table->date('upcoming_digital_plan_start_date')->nullable();
            $table->date('upcoming_digital_plan_expiry_date')->nullable();
            $table->string('webmail_status')->nullable();
            $table->integer('sms_plan')->default(0)->nullable();
            $table->string('auth_type')->default('EmailEasyOrder');
            $table->integer('pay_noti')->default(1);
            $table->tinyInteger('call_status')->default(0);
            $table->string('sms_status', 191)->default('0');
            $table->tinyInteger('pay_mail_status')->default(0);
            $table->boolean('setup_status')->default(0)->comment('0=Not Buy|1=Buy');
            $table->tinyInteger('isDomainDelete')->default(0)->comment('0=Not Delete|1=Delete');
            $table->tinyInteger('isCFileDelete')->default(0)->comment('0=Not Delete|1=Delete');
            $table->string('analytic_email', 191)->nullable();
            $table->longText('bkash_token')->nullable();
            $table->string('alert_popup', 191)->nullable();
            $table->bigInteger('access_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
