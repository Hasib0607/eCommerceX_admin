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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_chat_support')->after('payment_processing_charge')->default(0);
            $table->decimal('upload_file_limit')->after('monthly_chat_support')->default(0);
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
            if (Schema::hasColumn('plans', 'monthly_chat_support')) {
                $table->dropColumn('monthly_chat_support');
            }
            if (Schema::hasColumn('plans', 'upload_file_limit')) {
                $table->dropColumn('upload_file_limit');
            }
        });
    }
};
