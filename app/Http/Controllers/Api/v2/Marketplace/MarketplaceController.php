<?php

namespace App\Http\Controllers\Api\v2\Marketplace;

use App\Models\AcceptedPseProductRequest;
use App\Models\Pse\PseVisitorCounter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    /**
     * Retrieve products query.
     *
     * This method constructs a query to fetch products from the database,
     * specifically focusing on accepted PSE product requests.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getProductsQuery($category_id = null)
    {
        $today = Carbon::today();
        $tenDaysAfterExpiry = $today->copy()->addDays(10);

        $query = AcceptedPseProductRequest::leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
            ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
            ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
            ->select(
                'accepted_pse_product_requests.id',
                'accepted_pse_product_requests.product_id',
                'accepted_pse_product_requests.category_id',
                'products.name',
                'products.images AS productImage',
                'products.discount_type',
                'products.store_id',
                'products.regular_price',
                'products.promotional_price as discount_price',
                'stores.url as store_url'
            )
            ->selectRaw('COUNT(pse_visitor_counters.product_id) AS totalVisitor')
            ->orderByDesc('totalVisitor')
            ->orderBy('accepted_pse_product_requests.position', 'asc') // Order by position ascending
            ->orderByRaw("CASE WHEN stores.purchase_date = '$today' THEN 0 ELSE 1 END") // Prioritize today's purchases
            ->orderByRaw("CASE WHEN stores.purchase_date = 1 THEN 1 ELSE 0 END") // Prioritize active stores
            ->orderBy('products.created_at', 'desc') // Show latest products first
            ->where('products.pse', 2)
            ->where('accepted_pse_product_requests.status', 1)
            ->where(function ($query) use ($tenDaysAfterExpiry) {
                $query->whereNull('stores.expiry_date')
                    ->orWhere('stores.expiry_date', '>', $tenDaysAfterExpiry);
            })
            ->groupBy('accepted_pse_product_requests.product_id');

        if ($category_id !== null) {
            $query->whereJsonContains('category_id', $category_id);
        }

        return $query;
    }

    /**
     * Generate a standard response format for API.
     *
     * This method formats the API response including pagination details.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $response
     * @return array
     */
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

    /**
     * Generate an error response.
     *
     * This method creates an error response with the provided status code and message.
     *
     * @param int $statusCode
     * @param string $message
     * @return array
     */
    private function generateErrorResponse($statusCode, $message)
    {
        return [
            'status' => $statusCode,
            'error_message' => $message,
        ];
    }

    /**
     * Add slug, category names, and prepend image URLs to products.
     *
     * This method enhances each product object with slug, category names, and full image URLs.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $products
     * @return void
     */
    private function addSlugToProducts($products)
    {
        // Iterate over each product
        foreach ($products as $product) {
            // Convert category IDs to an array if it's a string
            $categoryIds = is_array($product->category_id) ? $product->category_id : json_decode($product->category_id, true);

            // Fetch category names based on category IDs
            $categoryNames = $this->getCategoryNames($categoryIds);

            // Get all category id as array
            $product->category_id = json_decode($product->category_id);

            // Assign category names to the product
            $product->category_names = $categoryNames;

            // Prepend base URL and path to each image filename
            $product->productImage = $this->prependImageURLs($product->productImage);

            // Generate slug for the product name
            $product->slug = generateSlug($product->name, '-');
        }
    }

    /**
     * Fetch category names based on category IDs.
     *
     * This method retrieves category names corresponding to given category IDs.
     *
     * @param array $categoryIds
     * @return array
     */
    private function getCategoryNames($categoryIds)
    {
        $categoryNames = [];

        // Iterate over each category ID
        foreach ($categoryIds as $categoryId) {
            // Fetch the category by ID
            $category = Category::find($categoryId); // Assuming Category is your model name
            // If category exists, add its name to the array
            if ($category) {
                $categoryNames[] = $category->name;
            }
        }

        return $categoryNames;
    }

    /**
     * Prepend base URL and path to each image filename.
     *
     * This method prepends base URL and path to each image filename in the provided string.
     *
     * @param string $imageString
     * @return array
     */
    private function prependImageURLs($imageString)
    {
        // Explode the comma-separated image string into an array
        $images = explode(',', $imageString);

        // Map each image to its full URL
        return array_map(function ($image) {
            // If the image is empty or null, use a default image URL
            return empty($image) ? url('/assets/images/eBitans_store.jpg') : url('/assets/images/product/' . $image);
        }, $images);
    }

    /**
     * Manipulate category icons for display.
     *
     * This method enhances category objects with full image URLs for icons and banners.
     *
     * @param \Illuminate\Support\Collection $categories
     * @return void
     */
    private function manipulateCategoryIcons(&$categories)
    {
        foreach ($categories as $category) {
            $category->icon = empty($category->icon) ? url('/assets/images/icon/default_category_icon.jpg') : url('/assets/images/icon/' . $category->icon);
            $category->banner = empty($category->banner) ? url('/assets/images/icon/default_category_icon.jpg') : url('/assets/images/category/' . $category->banner);
        }
    }

    /**
     * Calculate the total number of products for each category.
     *
     * This method iterates through the given categories and counts the total number of products
     * associated with each category using the AcceptedPseProductRequest model. The count is then
     * assigned to the 'totalProducts' property of each category object.
     *
     * @param \Illuminate\Database\Eloquent\Collection $categories The collection of categories to count products for.
     * @return void
     */
    protected function productCount($catagories)
    {
        foreach ($catagories as $cat) {
            $cat->totalProduct = AcceptedPseProductRequest::where('category_id', 'LIKE', '%"' . $cat->id . '"%')
                ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')->where('products.status', '!=', 'RecycleBin')
                ->count();
        }
    }

    /**
     * Retrieve paginated list of products.
     *
     * This method fetches a paginated list of products and returns as JSON response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $products = $this->getProductsQuery()->paginate(10);

            $this->addSlugToProducts($products);

            return response()->json($this->generateResponse($products));
        } catch (\Exception $e) {
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }

    /**
     * Retrieve all categories with total products count.
     *
     * This method fetches all categories with the count of associated products
     * and returns as JSON response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllCategories()
    {
        try {
            $categories = Category::select([
                'id',
                'name',
                'slug',
                'icon',
                'banner',
                'status',
                'position'
            ])
                ->whereNull('store_id')
                ->whereNull('customer_id')
                ->where('status', '!=', 'RecycleBin')
                ->orderBy('categories.position', 'asc')
                ->groupBy('id', 'name', 'icon', 'banner', 'status', 'position')
                ->get();

            // Count total product each one category
            $this->productCount($categories);

            // Manipulate the category icons
            $this->manipulateCategoryIcons($categories);

            // Transform the collection to include the "total_products" field
            $transformedCategories = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'icon' => $category->icon,
                    'banner' => $category->banner,
                    'status' => $category->status == "active" ? 1 : 0,
                    'position' => $category->position,
                    'total_products' => $category->totalProduct,
                ];
            });

            return response()->json([
                'status' => 200,
                'total' => $transformedCategories->count(),
                'results' => $transformedCategories,
            ]);
        } catch (\Exception $e) {
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }

    /**
     * Search products by name.
     *
     * This method searches products by name and returns matching results as JSON response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchProductByName(Request $request)
    {
        try {
            $name = trim($request->name);

            if (empty($name)) {
                return response()->json([
                    'status' => 200,
                    'message' => 'Your Name Field Are Empty.'
                ]);
            }

            $products = $this->getProductsQuery();
            $results = $products->where(function ($query) use ($name) {
                $query->where('products.name', 'LIKE', '%' . $name . '%');
            })->paginate(10);

            $this->addSlugToProducts($results);

            return response()->json($this->generateResponse($results));
        } catch (\Exception $e) {
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }

    /**
     * Search products by name within a specific category.
     *
     * This method searches products by name within a specific category
     * and returns matching results as JSON response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchProductIdAndName(Request $request)
    {
        try {
            $name = $request->name;
            $slug = $request->slug;

            $today = Carbon::today();

            $category = Category::where('slug', $slug)->first();

            if (!$category) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Category Not Found.'
                ]);
            }

            $category_id = $category->id;

            $products = AcceptedPseProductRequest::select(
                'accepted_pse_product_requests.id',
                'accepted_pse_product_requests.product_id',
                'accepted_pse_product_requests.category_id',
                'products.name',
                'products.images as productImage',
                'products.discount_type',
                'products.store_id',
                'products.regular_price',
                'products.promotional_price as discount_price',
                'stores.url as store_url',
                DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor')
            )
                ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
                ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
                ->where('accepted_pse_product_requests.category_id', 'LIKE', '%' . $category_id . '%')
                ->whereDate('stores.expiry_date', '>=', Carbon::now())
                ->when($name, function ($query, $name) {
                    return $query->where('products.name', 'LIKE', '%' . $name . '%');
                })
                ->groupBy(
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.images',
                    'products.discount_type',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price',
                    'stores.url'
                )
                ->orderBy('totalVisitor');

            $perPage = 10;
            $results = $products->paginate($perPage);

            $this->addSlugToProducts($results);

            return response()->json($this->generateResponse($results));
        } catch (\Exception $e) {
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }

    /**
     * Handles the visitor counter functionality.
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse JSON response indicating success or failure.
     */
    public function visitorCounter(Request $request)
    {
        try {
            // Extract request data
            $pseId = (int)$request->pse_id;
            $storeId = (int)$request->store_id;
            $storeUrl = (int)$request->domain;
            $productId = (int)$request->product_id;
            $ipAddress = $request->ip;

            // Check if the provided PSE product ID is valid
            $findPseProduct = AcceptedPseProductRequest::find($pseId);
            if (is_null($findPseProduct)) {
                return response()->json($this->generateErrorResponse(400, "Please input valid PSE product ID."));
            }

            // Check if the provided store URL is valid
            $findStoreUrl = Store::where('url', $storeUrl)->first();
            if (is_null($findStoreUrl)) {
                return response()->json($this->generateErrorResponse(400, "Please input valid store URL."));
            }

            // Check if the provided store ID is valid
            $findStoreId = Store::where('id', $storeId)->first();
            if (is_null($findStoreId)) {
                return response()->json($this->generateErrorResponse(400, "Please input valid store ID."));
            }

            // Check if the provided product ID is valid
            $findProductOrNot = Product::where('id', $productId)->first();
            if (is_null($findProductOrNot)) {
                return response()->json($this->generateErrorResponse(400, "Please input valid product ID."));
            }

            // Validate IP address
            if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                return response()->json($this->generateErrorResponse(400, "Invalid IP address"));
            }

            // Create a new visitor record
            $visitor = new PseVisitorCounter();
            $visitor->ip = $ipAddress;
            $visitor->appr_id = $findPseProduct->id;
            $visitor->product_id = $findProductOrNot->id;
            $visitor->store_id = $findStoreId->id;
            $visitor->store_url = $findStoreUrl->url;

            // If not save the visitor record
            if (!$visitor->save()) {
                return response()->json($this->generateErrorResponse(500, "Internal server error. Please try again later."));
            }

            // Return success response
            return response()->json($this->generateErrorResponse(200, "Visitor info stored successfully."));
        } catch (\Exception $e) {
            // Handle exceptions and return error response
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }

    protected function getCategoryProducts($categoryId)
    {
        return AcceptedPseProductRequest::select(
            'accepted_pse_product_requests.id',
            'accepted_pse_product_requests.product_id',
            'accepted_pse_product_requests.category_id',
            'products.name',
            'products.images as productImage',
            'products.discount_type',
            'products.store_id',
            'products.regular_price',
            'products.promotional_price as discount_price',
            'stores.url as store_url',
            \DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor')
        )
            ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
            ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
            ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
            ->where('category_id', 'like', '%".$categoryId."%')
            ->where('accepted_pse_product_requests.status', 1)
            ->groupBy('accepted_pse_product_requests.id',
                'accepted_pse_product_requests.product_id',
                'accepted_pse_product_requests.category_id',
                'products.name',
                'products.images',
                'products.discount_type',
                'products.store_id',
                'products.regular_price',
                'products.promotional_price',
                'stores.url')
            ->orderBy('totalVisitor');
    }

    /**
     * Retrieves category products based on different criteria such as category ID or total sales.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categoryProduct(Request $request)
    {
        try {
            // Retrieve category slug from request
            $name = $request->name;
            $slug = $request->slug;
            // Fetch category based on slug
            $category = Category::where('slug', $slug)->whereNull('store_id')->whereNull('customer_id')->where('status', '!=', 'RecycleBin')->first();

            // Check if category exists
            if (!$category) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Category Not Found.'
                ]);
            }

            // Retrieve products based on category
            // $products = $this->getCategoryProducts();

            // Initialize variable to store category products
            $categoryProducts = null;

            // Check if category ID matches a specific value
            if ($category->id === 28619) {
                $category_id = $category->id;

                // Filter products based on category ID
                $getAllProduct = AcceptedPseProductRequest::select(
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.images as productImage',
                    'products.discount_type',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price as discount_price',
                    'stores.url as store_url',
                    \DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor')
                )
                    ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
                    ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                    ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
                    ->where('category_id', 'like', '%".$category_id."%')
                    ->where('accepted_pse_product_requests.status', 1)
                    ->groupBy('accepted_pse_product_requests.id',
                        'accepted_pse_product_requests.product_id',
                        'accepted_pse_product_requests.category_id',
                        'products.name',
                        'products.images',
                        'products.discount_type',
                        'products.store_id',
                        'products.regular_price',
                        'products.promotional_price',
                        'stores.url')
                    ->orderBy('totalVisitor');

                $categoryProducts = $getAllProduct->where(function ($query) use ($category_id, $name) {
                    $query->where('accepted_pse_product_requests.category_id', 'like', '%' . $category_id . '%')
                        ->where('products.name', 'like', '%' . $name . '%');
                })->orderBy('products.store_id', 'desc')->orderBy('accepted_pse_product_requests.id', 'desc');
            }

            // Check if category ID matches another specific value
            if ($category->id === 28622) {
                $category_id = $category->id;

                $getAllProduct = AcceptedPseProductRequest::select(
                    'accepted_pse_product_requests.id',
                    'accepted_pse_product_requests.product_id',
                    'accepted_pse_product_requests.category_id',
                    'products.name',
                    'products.images as productImage',
                    'products.discount_type',
                    'products.store_id',
                    'products.regular_price',
                    'products.promotional_price as discount_price',
                    'stores.url as store_url',
                    \DB::raw('COUNT(pse_visitor_counters.product_id) as totalVisitor')
                )
                    ->leftJoin('products', 'products.id', '=', 'accepted_pse_product_requests.product_id')
                    ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                    ->leftJoin('pse_visitor_counters', 'pse_visitor_counters.product_id', '=', 'accepted_pse_product_requests.product_id')
                    ->where('category_id', 'like', '%".$category_id."%')
                    ->where('accepted_pse_product_requests.status', 1)
                    ->groupBy('accepted_pse_product_requests.id',
                        'accepted_pse_product_requests.product_id',
                        'accepted_pse_product_requests.category_id',
                        'products.name',
                        'products.images',
                        'products.discount_type',
                        'products.store_id',
                        'products.regular_price',
                        'products.promotional_price',
                        'stores.url')
                    ->orderBy('totalVisitor');

                // Retrieve products based on total sales
                $categoryProducts = $getAllProduct->where(function ($query) use ($category_id, $name) {
                    $query->where('accepted_pse_product_requests.category_id', 'like', '%' . $category_id . '%')
                        ->where('products.name', 'like', '%' . $name . '%');
                })->leftJoin('orderitems', 'orderitems.product_id', '=', 'accepted_pse_product_requests.product_id')
                    ->selectRaw('COUNT(orderitems.product_id) AS totalOrders')
                    ->orderByDesc('totalOrders')
                    ->orderBy('products.store_id', 'desc')
                    ->orderBy('accepted_pse_product_requests.id', 'desc')
                    ->havingRaw('totalOrders != 0');
            }

            // Set pagination limit
            $perPage = 10;

            // Paginate the results
            $results = $categoryProducts->paginate($perPage);

            // Add slug to products
            $this->addSlugToProducts($results);

            // Return success response with paginated products
            return response()->json($this->generateResponse($results));
        } catch (\Exception $e) {
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }

    public function topPicProduct(Request $request)
    {
        try {
            $ip = $request->ip;
            $name = $request->name;
            // $visitor = PseVisitorCounter::where('ip', $ip)->first();

            // if (!$visitor) {
            //     return response()->json([
            //         'status' => 404,
            //         'message' => 'Visitor Not Found.'
            //     ]);
            // }

            $visitorCounters = PseVisitorCounter::select(
                'pse_visitor_counters.appr_id',
                'pse_visitor_counters.product_id',
                'products.name',
                'products.tags',
                'products.images AS productImage',
                'products.discount_type',
                'products.store_id',
                'products.regular_price',
                'products.promotional_price as discount_price',
                'stores.url as store_url'
            )
                ->selectRaw('COUNT(pse_visitor_counters.product_id) AS totalVisit')
                ->leftJoin('products', 'products.id', '=', 'pse_visitor_counters.product_id')
                ->leftJoin('stores', 'stores.id', '=', 'products.store_id')
                // ->where('ip', $visitor->ip)
                ->where('ip', $request->ip)
                ->groupBy('pse_visitor_counters.product_id')
                ->orderByDesc('totalVisit');

            if (!empty($name)) {
                $visitorCounters = $visitorCounters->where(function ($query) use ($name) {
                    $query->where('products.name', 'like', '%' . $name . '%');
                });
            }

            $results = $visitorCounters->paginate(10);

            // Modify the productImage field to contain an array of URLs
            $results->getCollection()->transform(function ($images) {
                $images->productImage = $this->prependImageURLs($images->productImage);
                return $images;
            });

            return response()->json($this->generateResponse($results));
        } catch (\Exception $e) {
            return response()->json($this->generateErrorResponse(500, $e->getMessage()));
        }
    }
}
