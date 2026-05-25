<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront Bootstrap Cache
    |--------------------------------------------------------------------------
    |
    | The storefront bootstrap payload powers the first render of public store
    | pages. Keep this cache in Redis in production so repeated visits do not
    | rebuild the full homepage payload from many database queries.
    |
    */

    'bootstrap_cache_ttl' => (int) env('STOREFRONT_BOOTSTRAP_CACHE_TTL', 120),
    'bootstrap_product_limit' => (int) env('STOREFRONT_BOOTSTRAP_PRODUCT_LIMIT', 8),
    'bootstrap_slider_limit' => (int) env('STOREFRONT_BOOTSTRAP_SLIDER_LIMIT', 3),
    'bootstrap_banner_limit' => (int) env('STOREFRONT_BOOTSTRAP_BANNER_LIMIT', 6),
];
