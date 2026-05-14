<?php

namespace Database\Seeders;

use App\Models\SaasFeature;
use Illuminate\Database\Seeder;

class SaasFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['key' => 'pages.dashboard', 'name' => 'Dashboard page', 'type' => 'page'],
            ['key' => 'pages.products', 'name' => 'Products page', 'type' => 'page'],
            ['key' => 'pages.products.add', 'name' => 'Add product page', 'type' => 'page'],
            ['key' => 'pages.products.categories', 'name' => 'Categories page', 'type' => 'page'],
            ['key' => 'pages.products.subcategories', 'name' => 'Sub categories page', 'type' => 'page'],
            ['key' => 'pages.products.variants', 'name' => 'Variants page', 'type' => 'page'],
            ['key' => 'pages.products.brands', 'name' => 'Brands page', 'type' => 'page'],
            ['key' => 'pages.mediaLibrary', 'name' => 'Media library page', 'type' => 'page'],
            ['key' => 'pages.inventory', 'name' => 'Inventory page', 'type' => 'page'],
            ['key' => 'pages.customers', 'name' => 'Customers page', 'type' => 'page'],
            ['key' => 'pages.accountPayment', 'name' => 'Account payment page', 'type' => 'page'],
            ['key' => 'pages.notifications', 'name' => 'Notifications page', 'type' => 'page'],
            ['key' => 'pages.settings', 'name' => 'Settings page', 'type' => 'page'],

            ['key' => 'actions.products.create', 'name' => 'Create product', 'type' => 'action'],
            ['key' => 'actions.products.update', 'name' => 'Update product', 'type' => 'action'],
            ['key' => 'actions.products.delete', 'name' => 'Delete product', 'type' => 'action'],
            ['key' => 'actions.products.export', 'name' => 'Export products', 'type' => 'action'],

            ['key' => 'actions.catalog.categories.create', 'name' => 'Create category', 'type' => 'action'],
            ['key' => 'actions.catalog.categories.update', 'name' => 'Update category', 'type' => 'action'],
            ['key' => 'actions.catalog.categories.delete', 'name' => 'Delete category', 'type' => 'action'],
            ['key' => 'actions.catalog.categories.reorder', 'name' => 'Reorder category', 'type' => 'action'],
            ['key' => 'actions.catalog.categories.export', 'name' => 'Export category', 'type' => 'action'],

            ['key' => 'actions.catalog.brands.create', 'name' => 'Create brand', 'type' => 'action'],
            ['key' => 'actions.catalog.brands.update', 'name' => 'Update brand', 'type' => 'action'],
            ['key' => 'actions.catalog.brands.delete', 'name' => 'Delete brand', 'type' => 'action'],
            ['key' => 'actions.catalog.brands.export', 'name' => 'Export brand', 'type' => 'action'],

            ['key' => 'actions.catalog.variants.create', 'name' => 'Create variant', 'type' => 'action'],
            ['key' => 'actions.catalog.variants.update', 'name' => 'Update variant', 'type' => 'action'],
            ['key' => 'actions.catalog.variants.delete', 'name' => 'Delete variant', 'type' => 'action'],
            ['key' => 'actions.catalog.variants.reorder', 'name' => 'Reorder variant', 'type' => 'action'],

            ['key' => 'actions.media.view', 'name' => 'View media asset', 'type' => 'action'],
            ['key' => 'actions.media.upload', 'name' => 'Upload media asset', 'type' => 'action'],
            ['key' => 'actions.media.delete', 'name' => 'Delete media asset', 'type' => 'action'],
            ['key' => 'actions.media.bulkDelete', 'name' => 'Bulk delete media', 'type' => 'action'],

            ['key' => 'quota.products.max', 'name' => 'Max products', 'type' => 'quota', 'default_limit' => null],
        ];

        foreach ($features as $feature) {
            SaasFeature::query()->updateOrCreate(
                ['key' => $feature['key']],
                [
                    'name' => $feature['name'],
                    'type' => $feature['type'],
                    'enabled_by_default' => $feature['enabled_by_default'] ?? true,
                    'default_limit' => $feature['default_limit'] ?? null,
                ],
            );
        }
    }
}

