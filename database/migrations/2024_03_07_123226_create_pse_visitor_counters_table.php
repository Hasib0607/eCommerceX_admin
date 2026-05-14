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
        Schema::create('pse_visitor_counters', function (Blueprint $table) {
            $table->id();
            $table->string('ip');
            $table->string('appr_id');
            $table->string('product_id');
            $table->string('store_id');
            $table->string('store_url');
            $table->string('status');
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
        Schema::dropIfExists('pse_visitor_counters');
    }
};
