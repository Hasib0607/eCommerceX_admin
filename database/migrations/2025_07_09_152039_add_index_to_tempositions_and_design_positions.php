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
        Schema::table('tempositions', function (Blueprint $table) {
            $table->index('template_id', 'idx_template_id');
        });

        Schema::table('design_positions', function (Blueprint $table) {
            $table->index('store_id', 'idx_store_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tempositions', function (Blueprint $table) {
            $table->dropIndex('idx_template_id');
        });

        Schema::table('design_positions', function (Blueprint $table) {
            $table->dropIndex('idx_store_id');
        });
    }
};
