<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->index('stores', ['url', 'expiry_date'], 'idx_stores_url_expiry');
        $this->index('designs', ['store_id'], 'idx_designs_store');
        $this->index('headersettings', ['store_id'], 'idx_headersettings_store');
        $this->index('design_positions', ['store_id', 'position'], 'idx_design_positions_store_position');
        $this->index('tempositions', ['template_id', 'position'], 'idx_tempositions_template_position');
        $this->index('menus', ['store_id', 'sort'], 'idx_menus_store_sort');
        $this->index('pages', ['store_id', 'status'], 'idx_pages_store_status');
        $this->index('categories', ['store_id', 'parent', 'status', 'position'], 'idx_categories_store_parent_status_position');
        $this->index('sliders', ['store_id', 'status', 'position'], 'idx_sliders_store_status_position');
        $this->index('banners', ['store_id', 'status', 'type'], 'idx_banners_store_status_type');
        $this->index('products', ['store_id', 'status', 'position'], 'idx_products_store_status_position');
        $this->index('products', ['store_id', 'status', 'feature', 'position'], 'idx_products_store_status_feature_position');
        $this->index('products', ['store_id', 'status', 'best_sell', 'position'], 'idx_products_store_status_best_position');
        $this->index('products', ['store_id', 'status', 'created_at'], 'idx_products_store_status_created');
        $this->index('buy_moduluses', ['store_id', 'modulus_id'], 'idx_buy_moduluses_store_modulus');
        $this->index('moduluses', ['status'], 'idx_moduluses_status');
        $this->index('quick_logins', ['store_id', 'modulus_id'], 'idx_quick_logins_store_modulus');
    }

    public function down(): void
    {
        foreach ([
            'stores' => ['idx_stores_url_expiry'],
            'designs' => ['idx_designs_store'],
            'headersettings' => ['idx_headersettings_store'],
            'design_positions' => ['idx_design_positions_store_position'],
            'tempositions' => ['idx_tempositions_template_position'],
            'menus' => ['idx_menus_store_sort'],
            'pages' => ['idx_pages_store_status'],
            'categories' => ['idx_categories_store_parent_status_position'],
            'sliders' => ['idx_sliders_store_status_position'],
            'banners' => ['idx_banners_store_status_type'],
            'products' => [
                'idx_products_store_status_position',
                'idx_products_store_status_feature_position',
                'idx_products_store_status_best_position',
                'idx_products_store_status_created',
            ],
            'buy_moduluses' => ['idx_buy_moduluses_store_modulus'],
            'moduluses' => ['idx_moduluses_status'],
            'quick_logins' => ['idx_quick_logins_store_modulus'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                if ($this->hasIndex($table, $index)) {
                    Schema::table($table, fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
        }
    }

    private function index(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $table) => $table->index($columns, $name));
    }

    private function hasIndex(string $table, string $name): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $database = DB::getDatabaseName();
        $rows = DB::select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$database, $table, $name]
        );

        return count($rows) > 0;
    }
};
