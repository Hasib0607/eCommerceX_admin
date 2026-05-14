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
        Schema::table('products_and_veriants_tables', function (Blueprint $table) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('status', 'idx_status');
                $table->index('category', 'idx_category');
                $table->index('subcategory', 'idx_sub');
                $table->index('brand', 'idx_brand');
                $table->index('store_id', 'idx_store');
            });

            Schema::table('veriants', function (Blueprint $table) {
                $table->index(['pid', 'color'], 'idx_pid_color');
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products_and_veriants_tables', function (Blueprint $table) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('idx_status');
                $table->dropIndex('idx_category');
                $table->dropIndex('idx_sub');
                $table->dropIndex('idx_brand');
                $table->dropIndex('idx_store');
            });

            Schema::table('veriants', function (Blueprint $table) {
                $table->dropIndex('idx_pid_color');
            });
        });
    }
};
