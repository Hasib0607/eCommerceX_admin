<?php

namespace App\Http\Controllers\Api\v2;

use Illuminate\Database\QueryException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\AdPse;

class PseAdsController extends Controller
{
    private function generateResponse($response)
    {
        return [
            'status' => 200,
            'total' => $response->total(),
            'per_page' => $response->perPage(),
            'current_page' => $response->currentPage(),
            'results' => $response->items(),
            'next_page_url' => $response->nextPageUrl(),
            'prev_page_url' => $response->previousPageUrl()
        ];
    }

    private function manipulateCategoryIcons($items)
    {
        $categories = Category::select('slug', 'id')
            ->whereNull('store_id')
            ->whereNull('customer_id')
            ->where('status', '!=', 'RecycleBin')
            ->get()
            ->pluck('slug', 'id')
            ->toArray();

        foreach ($items as $item) {
            $item->banner = empty ($item->banner)
                ? URL::to('/') . '/assets/images/icon/default_category_icon.jpg'
                : URL::to('/') . '/assets/images/ads_pse_image/' . $item->banner;

            $item->image_type = ($item->image_type !== null) ? ($item->image_type == 0 ? 'Landscape' : 'Portrait') : 'Unknown';

            // Check if the category_id is an array
            $categoryIds = is_array($item->category_id) ? $item->category_id : explode(',', $item->category_id);
            $category_slugs = [];
            foreach ($categoryIds as $categoryId) {
                $category_slugs[] = isset ($categories[$categoryId]) ? $categories[$categoryId] : 'slug not found';
            }

            $item->category_slugs = $category_slugs;
        }
    }

    public function index()
    {
        try {
            $perPage = 20;
            $result = AdPse::select('id', 'name', 'link', 'category_id', 'banner', 'status', 'position', 'image_type')
                ->where('status', 1)
                ->orderBy('position', 'asc')
                ->paginate($perPage);
            $this->manipulateCategoryIcons($result);

            // Build the response
            return response()->json($this->generateResponse($result));
        } catch (QueryException $e) {
            // Handle database query exception
            $response = [
                'status' => 500,
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            // Handle other exceptions
            $response = [
                'status' => 500,
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
