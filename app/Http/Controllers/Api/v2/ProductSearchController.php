<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ProductSearchService;

class ProductSearchController extends Controller
{
    protected $service;

    public function __construct(ProductSearchService $service)
    {
        $this->service = $service;
    }

    public function search(Request $request)
    {
        $query = trim($request->input('query')) ?? "";
        $page = max(1, (int)($request->input('page', 1)));
        $category = trim($request->input('slug')) ?? "all-products";
        $minSearch = $request->boolean('minSearch', false);

        if (is_null($category) || empty($category)) {
            $category = "all-products";
        }

        if (isProbablyDomainOrUrl($query)) {
            $data = $this->service->fetchByUrl($query);
        } else {
            $data = $this->service->search($query, $page, $category, $minSearch);
        }

        return sendResponse("Success", $data);
    }

}
