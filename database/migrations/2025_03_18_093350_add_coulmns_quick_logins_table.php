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
        Schema::table('quick_logins', function (Blueprint $table) {
            $table->longText('test_event_code')->after('general_access_token')->nullable();
            $table->longText('domain_verification_code')->after('test_event_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quick_logins', function (Blueprint $table) {
            if (Schema::hasColumn('quick_logins', 'test_event_code')) {
                $table->dropColumn('test_event_code');
            }
            if (Schema::hasColumn('quick_logins', 'domain_verification_code')) {
                $table->dropColumn('domain_verification_code');
            }
        });
    }
};
