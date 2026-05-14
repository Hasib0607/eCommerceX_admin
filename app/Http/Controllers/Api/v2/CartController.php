<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Cart;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    private $user_id = NULL;

    public function __construct()
    {
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            $this->user_id = $user->id ?? NULL;
        }
    }

    public function addToCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => ['required', 'integer', 'exists:stores,id'],
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'qty' => ['required', 'numeric', 'min:0', 'max:999999.99'],
                'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'variant_id' => ['nullable', 'integer', 'exists:veriants,id'],
            ], [
                'store_id.required' => 'A store selection is required.',
                'store_id.integer' => 'Store ID must be an integer value.',
                'store_id.exists' => 'The selected store does not exist.',

                'product_id.required' => 'A product selection is required.',
                'product_id.integer' => 'Product ID must be an integer value.',
                'product_id.exists' => 'The selected product does not exist.',

                'qty.required' => 'Quantity is required.',
                'qty.numeric' => 'Quantity must be a numeric value.',
                'qty.min' => 'Quantity cannot be negative.',
                'qty.max' => 'Quantity exceeds maximum allowed value.',

                'price.required' => 'Price is required.',
                'price.numeric' => 'Price must be a numeric value.',
                'price.min' => 'Price cannot be negative.',
                'price.max' => 'Price exceeds maximum allowed value.',

                'variant_id.integer' => 'Variant ID must be an integer value.',
                'variant_id.exists' => 'The selected variant does not exist.',
            ]);

            // Custom validation - at least one identifier required
            $validator->after(function ($validator) use ($request) {
                if (is_null($this->user_id) && is_null($this->getSessionID($request))) {
                    $validator->errors()->add(
                        'general',
                        'At least one identifier (user or session) is required.'
                    );
                }
            });

            if ($validator->fails()) {
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }

            // Get product information
            $product = Product::findOrFail($request->product_id);

            // Check for existing item with same product/variant
            $existingCart = $this->findExistingCartItem($request);

            if ($existingCart) {
                // Update existing item
                $totalQty = $request->qty;

                // Check against product maximum
                if ($product->quantity > 0 && $totalQty > $product->quantity) {
                    return sendError("Out of Stock", [
                        'available' => $product->stock_quantity,
                        'requested' => $request->qty
                    ], 422);
                }


                // Update existing record
                $existingCart->update([
                    'qty' => $totalQty,
                    'price' => $request->price, // Update to latest price
                    'updated_at' => now()
                ]);

                return sendResponse("Cart item quantity updated", ["data" => $existingCart]);
            }


            // Create cart record
            $abandonedCart = Cart::create([
                'store_id' => $request->store_id,
                'user_id' => $this->user_id,
                'ip_address' => $request->ip(), // Auto-fill IP if not provided
                'session_id' => $this->getSessionID($request),
                'product_id' => $request->product_id,
                'qty' => $request->qty,
                'price' => $request->price,
                'variant_id' => $request->variant_id,
            ]);

            return sendResponse("Item added to cart successfully", ["data" => $abandonedCart]);

        } catch (\Exception $e) {
            return serverError();
        }
    }

    protected function findExistingCartItem(Request $request)
    {
        // If cart_id is provided, update existing item
        if ($request->filled('cart_id')) {
            $cartItem = $this->findCartItem($request);

            if ($cartItem) {
                return $cartItem;
            }
        }

        return Cart::where('store_id', $request->store_id)
            ->where('product_id', $request->product_id)
            ->when($request->has('variant_id'), function ($query) use ($request) {
                // Only apply variant_id condition if it exists in request
                return $query->where('variant_id', $request->variant_id);
            }, function ($query) {
                // Otherwise match items with either same variant_id or null
                return $query->whereNull('variant_id');
            })
            ->when($this->user_id, function ($query) {
                // For authenticated users - match by user_id
                return $query->where('user_id', $this->user_id);
            }, function ($query) use ($request) {
                // For guests - match by either session_id OR ip_address
                return $query->whereNull('user_id')
                    ->where(function ($q) use ($request) {
                        // If session_id exists in request or can be obtained from session
                        if ($sessionId = $this->getSessionID($request)) {
                            $q->where('session_id', $sessionId);
                        }
                        // Always include ip_address check as fallback
                        $q->orWhere('ip_address', $request->ip());
                    });
            })
            ->first();
    }

    protected function findCartItem(Request $request)
    {
        return Cart::where('id', $request->cart_id)
            ->where('store_id', $request->store_id)
            ->first();
    }

    public function deleteCartItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cart_id' => ['required', 'integer', 'exists:carts,id'],
                'store_id' => ['required', 'integer', 'exists:stores,id'],
            ], [
                'cart_id.required' => 'Cart item ID is required.',
                'cart_id.integer' => 'Cart ID must be an integer.',
                'cart_id.exists' => 'The specified cart item does not exist.',
                'store_id.required' => 'Store ID is required.',
                'store_id.integer' => 'Store ID must be an integer.',
                'store_id.exists' => 'The specified store does not exist.',
            ]);

            if ($validator->fails()) {
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }

            $cartItem = $this->findCartItem($request);

            if (!$cartItem) {
                return sendError("Cart Item Not Found!", [], 404);
            }

            $cartItem->delete();

            return sendResponse("Cart item removed successfully", null);

        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function clearCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => ['required', 'integer', 'exists:stores,id'],
            ], [
                'store_id.required' => 'Store ID is required.',
                'store_id.integer' => 'Store ID must be an integer.',
                'store_id.exists' => 'The specified store does not exist.',
            ]);

            // Custom validation - at least one identifier required
            $validator->after(function ($validator) use ($request) {
                if (is_null($this->user_id) && is_null($this->getSessionID($request))) {
                    $validator->errors()->add(
                        'general',
                        'At least one identifier (user or session) is required.'
                    );
                }
            });

            if ($validator->fails()) {
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }


            $query = Cart::where('store_id', $request->store_id)
                ->when($this->user_id, function ($query) {
                    // For authenticated users - match by user_id
                    return $query->where('user_id', $this->user_id);
                }, function ($query) use ($request) {
                    // For guests - match by either session_id OR ip_address
                    return $query->whereNull('user_id')
                        ->where(function ($q) use ($request) {
                            // If session_id exists in request or can be obtained from session
                            if ($sessionId = $this->getSessionID($request)) {
                                $q->where('session_id', $sessionId);
                            }
                            // Always include ip_address check as fallback
                            $q->orWhere('ip_address', $request->ip());
                        });
                });

            $query->delete();

            return sendResponse("Cart cleared successfully.", null);

        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function addContactToCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => ['required', 'integer', 'exists:stores,id'],
                'phone' => ['required', 'string'],
            ], [
                'store_id.required' => 'Store ID is required.',
                'store_id.integer' => 'Store ID must be an integer.',
                'store_id.exists' => 'The specified store does not exist.',

                'phone.required' => 'Phone is required.',
            ]);

            // Custom validation - at least one identifier required
            $validator->after(function ($validator) use ($request) {
                if (is_null($this->user_id) && is_null($this->getSessionID($request))) {
                    $validator->errors()->add(
                        'general',
                        'At least one identifier (user or session) is required.'
                    );
                }
            });

            if ($validator->fails()) {
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }


            $query = Cart::where('store_id', $request->store_id)
                ->when($this->user_id, function ($query) {
                    // For authenticated users - match by user_id
                    return $query->where('user_id', $this->user_id);
                }, function ($query) use ($request) {
                    // For guests - match by either session_id OR ip_address
                    return $query->whereNull('user_id')
                        ->where(function ($q) use ($request) {
                            // If session_id exists in request or can be obtained from session
                            if ($sessionId = $this->getSessionID($request)) {
                                $q->where('session_id', $sessionId);
                            }
                            // Always include ip_address check as fallback
                            $q->orWhere('ip_address', $request->ip());
                        });
                });


            $query->update([
                'phone' => $request->phone ?? NULL,
                'email' => $request->email ?? NULL,
                'updated_at' => now()
            ]);

            return sendResponse("Cart updated successfully.", null);

        } catch (\Exception $e) {
            return serverError();
        }
    }

    public function getCartItems(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'store_id' => ['required', 'integer', 'exists:stores,id'],
            ], [
                'store_id.required' => 'Store ID is required.',
                'store_id.integer' => 'Store ID must be an integer.',
                'store_id.exists' => 'The specified store does not exist.',
            ]);

            if ($validator->fails()) {
                $errors = $validator->getMessageBag();
                return sendError("Validation Error", $errors, 422);
            }

            // Get cart items with product relationships
            $cartItems = Cart::with(['product', 'variant'])
                ->where('store_id', $request->store_id)
                ->when($this->user_id, function ($query) {
                    // For authenticated users - match by user_id
                    return $query->where('user_id', $this->user_id);
                }, function ($query) use ($request) {
                    // For guests - match by either session_id OR ip_address
                    return $query->whereNull('user_id')
                        ->where(function ($q) use ($request) {
                            // If session_id exists in request or can be obtained from session
                            if ($sessionId = $this->getSessionID($request)) {
                                $q->where('session_id', $sessionId);
                            }
                            // Always include ip_address check as fallback
                            $q->orWhere('ip_address', $request->ip());
                        });
                })
                ->get();

            $formattedCart = $this->formatCartResponse($cartItems);

            return sendResponse("Cart items retrieved successfully", ["data" => $formattedCart]);

        } catch (\Exception $e) {
            return serverError();
        }
    }


    public function getSessionID(Request $request)
    {
        // First try to get from header
        $sessionId = $request->header('X-App-Session-ID');

        // Fallback to request input if not in header
        $sessionId = $sessionId ?? $request->session_id;

        // Final fallback to session if available
        return $sessionId ??
            (method_exists($request, 'session') && $request->hasSession()
                ? $request->session()->getId()
                : null);
    }

    public function formatCartResponse($cartItems)
    {
        return $cartItems->map(function ($item) {
            $product = $item->product;

            $images = array_filter(explode(',', $product->images));
            $gallery_image = array_filter(explode(',', $product->gallery_image));
            $mergedImages = array_unique(array_merge($gallery_image, $images));
            $images = array_map(fn($img) => getPath($img, 'assets/images/product'), $mergedImages);

            $averageRating = $product->reviews_count > 0 ? $product->reviews_sum_rating / $product->reviews_count : 0;

            // Prepare variants if exists
            $variants = $product->getVariantsWithConversion($item->store_id)->get()->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'pid' => $variant->pid,
                    'color' => $variant->color,
                    'size' => $variant->size,
                    'volume' => $variant->volume,
                    'unit' => $variant->unit,
                    'quantity' => $variant->quantity,
                    'additional_price' => $variant->additional_price,
                    'image' => getPath($variant->image, 'assets/images/product'),
                    'color_image' => getPath($variant->color_image, 'assets/images/product'),
                    'symbol' => $variant->symbol,
                    'code' => $variant->code,
                ];
            });

            $discount_price = $product->regular_price <= $product->promotional_price ? "0" : $product->promotional_price;
            $calculate_regular_price = getPrice($product->regular_price, $discount_price, $product->discount_type);
            $campaign_offer = (new SubdomainController())->checkProductOffer($product, $calculate_regular_price, $item->store_id);

            return [
                'cart_id' => $item->id,
                'store_id' => $item->store_id,
                'qty' => (float)$item->qty,
                'price' => (float)$item->price,
                'total' => (float)($item->qty * $item->price),
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image' => $images,
                    'rating' => $averageRating,
                    'number_rating' => $product->reviews_count,
                    'slug' => generateSlug($product->name, '-'),
                    'description' => mb_substr($product->description, 0, 216),
                    'regular_price' => (float)$product->regular_price,
                    'calculate_regular_price' => (float)$calculate_regular_price ?? (float)$product->regular_price ?? "",
                    'product_offer' => $campaign_offer ?? "",
                    'discount_type' => $product->discount_type,
                    'discount_price' => (float)$discount_price,
                    'category' => $product->get_categories ?? "",
                    'subcategory' => $product->get_subcategories ?? "",
                    'tax_type' => $product->tax_type,
                    'tax_rate' => (float)$product->tax_rate,
                    'quantity' => (float)$product->quantity,
                    'stock_status' => $product->stock_status,
                    'SKU' => $product->SKU,
                    'tags' => $product->tags,
                    'symbol' => $product->symbol,
                    'code' => $product->code,
                    'variant' => $variants,
                    'brand' => $product->getBrand->name ?? "",
                    'supplier' => $product->getSupplier->name ?? ""
                ],
                'selected_variant' => $item->variant ? [
                    'id' => $item->variant->id,
                    'color' => $item->variant->color,
                    'size' => $item->variant->size,
                    'volume' => $item->variant->volume,
                    'unit' => $item->variant->unit,
                    'quantity' => $item->variant->quantity,
                    'additional_price' => $item->variant->additional_price,
                    'image' => getPath($item->variant->image, 'assets/images/product'),
                    'color_image' => getPath($item->variant->color_image, 'assets/images/product'),
                    'symbol' => $item->variant->symbol,
                    'code' => $item->variant->code,
                ] : null
            ];
        });
    }

//    public function checkProductOffer($product, $regular_price, $store_id)
//    {
//        $id = $product->id;
//        $currentDate = Carbon::now()->format('Y-m-d');
//        $currentDay = Carbon::now()->format('l');
//
//        // Common query base for campaigns
//        $baseQuery = Campaign::convertCurrency($store_id)
//            ->where('campaigns.status', 'active')
//            ->where('campaigns.store_id', $store_id);
//
//        // Date Range Campaigns (Campaign 1 & 2)
//        $dateRangeQuery = clone $baseQuery;
//        $dateRangeQuery->where('campaigns.length_type', 'date_range')
//            ->where('campaigns.start_date', '<=', $currentDate)
//            ->where('campaigns.end_date', '>=', $currentDate);
//
//        // Campaign 1: Product-specific
//        $campaign1 = (clone $dateRangeQuery)->where('campaigns.campaign_type', 'product')
//            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
//            ->get();
//        if ($response = $this->isCampaignActiveNow($campaign1, $regular_price)) {
//            return $response;
//        }
//
//        // Campaign 2: Category-specific
////        $productCategories = explode(',', $product->category); // Convert product categories to an array
////        $productSubcategories = isset($product->subcategory) ? explode(',', $product->subcategory) : []; // Convert subcategories if available
//
//        $productCategories = is_string($product->category) ? explode(',', $product->category) : (array)$product->category;
//        $productSubcategories = isset($product->subcategory)
//            ? (is_string($product->subcategory) ? explode(',', $product->subcategory) : (array)$product->subcategory)
//            : [];
//
//        $categoryQuery = (clone $dateRangeQuery)
//            ->where('campaigns.campaign_type', 'category')
//            ->where(function ($query) use ($productCategories, $productSubcategories) {
//                foreach ($productCategories as $category) {
//                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$category]);
//                }
//                foreach ($productSubcategories as $subcategory) {
//                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$subcategory]);
//                }
//            });
//
//        $campaign2 = $categoryQuery->get();
//        if ($response = $this->isCampaignActiveNow($campaign2, $regular_price)) {
//            return $response;
//        }
//
//        // Specific Date Campaigns (Campaign 3 & 4)
//        $specificDateQuery = clone $baseQuery;
//        $specificDateQuery->where('campaigns.length_type', 'specific_date')
//            ->where('campaigns.specific_dates', $currentDate);
//
//        // Campaign 3: Product-specific
//        $campaign3 = (clone $specificDateQuery)->where('campaigns.campaign_type', 'product')
//            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
//            ->get();
//        if ($response = $this->isCampaignActiveNow($campaign3, $regular_price)) {
//            return $response;
//        }
//
//        // Campaign 4: Category-specific
////        $categoryQuery = (clone $specificDateQuery)->where('campaigns.campaign_type', 'category')
////            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
////
////        if (isset($product->subcategory)) {
////            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
////        }
////        $productCategories = explode(',', $product->category); // Convert product categories to an array
////        $productSubcategories = isset($product->subcategory) ? explode(',', $product->subcategory) : []; // Convert subcategories if available
//
//        $productCategories = is_string($product->category) ? explode(',', $product->category) : (array)$product->category;
//        $productSubcategories = isset($product->subcategory)
//            ? (is_string($product->subcategory) ? explode(',', $product->subcategory) : (array)$product->subcategory)
//            : [];
//
//        $categoryQuery = (clone $specificDateQuery)
//            ->where('campaigns.campaign_type', 'category')
//            ->where(function ($query) use ($productCategories, $productSubcategories) {
//                foreach ($productCategories as $category) {
//                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$category]);
//                }
//                foreach ($productSubcategories as $subcategory) {
//                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$subcategory]);
//                }
//            });
//
//        $campaign4 = $categoryQuery->get();
//        if ($response = $this->isCampaignActiveNow($campaign4, $regular_price)) {
//            return $response;
//        }
//
//        // Repeat Date Campaigns (Campaign 5 & 6)
//        $repeatDateQuery = clone $baseQuery;
//        $repeatDateQuery->where('campaigns.length_type', 'repeat_date')
//            ->whereRaw('FIND_IN_SET("' . $currentDay . '", campaigns.repeat_dates)');
//
//        // Campaign 5: Product-specific
//        $campaign5 = (clone $repeatDateQuery)->where('campaigns.campaign_type', 'product')
//            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
//            ->get();
//        if ($response = $this->isCampaignActiveNow($campaign5, $regular_price)) {
//            return $response;
//        }
//
//        // Campaign 6: Category-specific
////        $categoryQuery = (clone $repeatDateQuery)->where('campaigns.campaign_type', 'category')
////            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
////
////        if (isset($product->subcategory)) {
////            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
////        }
//
////        $productCategories = explode(',', $product->category); // Convert product categories to an array
////        $productSubcategories = isset($product->subcategory) ? explode(',', $product->subcategory) : []; // Convert subcategories if available
//
//        $productCategories = is_string($product->category) ? explode(',', $product->category) : (array)$product->category;
//        $productSubcategories = isset($product->subcategory)
//            ? (is_string($product->subcategory) ? explode(',', $product->subcategory) : (array)$product->subcategory)
//            : [];
//
//        $categoryQuery = (clone $repeatDateQuery)
//            ->where('campaigns.campaign_type', 'category')
//            ->where(function ($query) use ($productCategories, $productSubcategories) {
//                foreach ($productCategories as $category) {
//                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$category]);
//                }
//                foreach ($productSubcategories as $subcategory) {
//                    $query->orWhereRaw("FIND_IN_SET(?, campaigns.category)", [$subcategory]);
//                }
//            });
//
//        $campaign6 = $categoryQuery->get();
//        if ($response = $this->isCampaignActiveNow($campaign6, $regular_price)) {
//            return $response;
//        }
//
//        return [
//            "status" => false,
//            "message" => "No active offers found",
//            "offer_price" => null,
//            "offer_amount" => null,
//            "discount_type" => null,
//            "discount_amount" => null,
//            "shipping_area" => null,
//        ];
//    }
//
//    /**
//     * Check if a campaign is currently active based on start and end times.
//     */
//    private function isCampaignActiveNow($campaigns, $regular_price)
//    {
//        $currentTime = Carbon::now()->format('H:i');
//
//        foreach ($campaigns as $campaign) {
//            if (isset($campaign->start_time, $campaign->end_time)) {
//                if ($campaign->start_time <= $currentTime && $campaign->end_time >= $currentTime) {
//                    return $this->generateOfferResponse($regular_price, $campaign);
//                }
//            } else {
//                return $this->generateOfferResponse($regular_price, $campaign);
//            }
//        }
//
//        return null;
//    }
//
//    /**
//     * Generate the offer response.
//     */
//    private function generateOfferResponse($regular_price, $campaign)
//    {
//        $offer_price = getPrice($regular_price, $campaign->discount_amount, $campaign->discount_type);
//        $discount_amount = getDiscountAmount($regular_price, $campaign->discount_amount, $campaign->discount_type);
//
//        return [
//            "status" => true,
//            "message" => "Success",
//            "offer_price" => $offer_price ?? null,
//            "offer_amount" => $discount_amount ?? null,
//            "discount_type" => $campaign->discount_type ?? null,
//            "discount_amount" => $campaign->discount_amount ?? null,
//            "shipping_area" => $campaign->shipping_area ?? null,
//        ];
//    }

//    public function checkProductOffer($product, $regular_price, $store_id)
//    {
//        $id = $product->id;
//        $currentDate = Carbon::now()->format('Y-m-d');
//        $currentDay = Carbon::now()->format('l');
//
//        // Common query base for campaigns
//        $baseQuery = Campaign::convertCurrency($store_id)
//            ->where('campaigns.status', 'active')
//            ->where('campaigns.store_id', $store_id);
//
//        // Date Range Campaigns (Campaign 1 & 2)
//        $dateRangeQuery = clone $baseQuery;
//        $dateRangeQuery->where('campaigns.length_type', 'date_range')
//            ->where('campaigns.start_date', '<=', $currentDate)
//            ->where('campaigns.end_date', '>=', $currentDate);
//
//        // Campaign 1: Product-specific
//        $campaign1 = (clone $dateRangeQuery)->where('campaigns.campaign_type', 'product')
//            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
//            ->get();
//        if ($response = $this->isCampaignActiveNow($campaign1, $regular_price)) {
//            return $response;
//        }
//
//        // Campaign 2: Category-specific
//        $categoryQuery = (clone $dateRangeQuery)->where('campaigns.campaign_type', 'category')
//            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
//
//        if (isset($product->subcategory)) {
//            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
//        }
//
//        $campaign2 = $categoryQuery->get();
//        if ($response = $this->isCampaignActiveNow($campaign2, $regular_price)) {
//            return $response;
//        }
//
//        // Specific Date Campaigns (Campaign 3 & 4)
//        $specificDateQuery = clone $baseQuery;
//        $specificDateQuery->where('campaigns.length_type', 'specific_date')
//            ->where('campaigns.specific_dates', $currentDate);
//
//        // Campaign 3: Product-specific
//        $campaign3 = (clone $specificDateQuery)->where('campaigns.campaign_type', 'product')
//            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
//            ->get();
//        if ($response = $this->isCampaignActiveNow($campaign3, $regular_price)) {
//            return $response;
//        }
//
//        // Campaign 4: Category-specific
//        $categoryQuery = (clone $specificDateQuery)->where('campaigns.campaign_type', 'category')
//            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
//
//        if (isset($product->subcategory)) {
//            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
//        }
//
//        $campaign4 = $categoryQuery->get();
//        if ($response = $this->isCampaignActiveNow($campaign4, $regular_price)) {
//            return $response;
//        }
//
//        // Repeat Date Campaigns (Campaign 5 & 6)
//        $repeatDateQuery = clone $baseQuery;
//        $repeatDateQuery->where('campaigns.length_type', 'repeat_date')
//            ->whereRaw('FIND_IN_SET("' . $currentDay . '", campaigns.repeat_dates)');
//
//        // Campaign 5: Product-specific
//        $campaign5 = (clone $repeatDateQuery)->where('campaigns.campaign_type', 'product')
//            ->whereRaw('FIND_IN_SET("' . $id . '", campaigns.products)')
//            ->get();
//        if ($response = $this->isCampaignActiveNow($campaign5, $regular_price)) {
//            return $response;
//        }
//
//        // Campaign 6: Category-specific
//        $categoryQuery = (clone $repeatDateQuery)->where('campaigns.campaign_type', 'category')
//            ->whereRaw('FIND_IN_SET("' . (int)$product->category . '", campaigns.category)');
//
//        if (isset($product->subcategory)) {
//            $categoryQuery->orWhereRaw('FIND_IN_SET("' . (int)$product->subcategory . '", campaigns.category)');
//        }
//
//        $campaign6 = $categoryQuery->get();
//        if ($response = $this->isCampaignActiveNow($campaign6, $regular_price)) {
//            return $response;
//        }
//
//        return [
//            "status" => false,
//            "message" => "No active offers found",
//            "offer_price" => null
//        ];
//    }
//
//    private function isCampaignActiveNow($campaigns, $regular_price)
//    {
//        $currentTime = Carbon::now()->format('H:i');
//
//        foreach ($campaigns as $campaign) {
//            if (isset($campaign->start_time, $campaign->end_time)) {
//                if ($campaign->start_time <= $currentTime && $campaign->end_time >= $currentTime) {
//                    return $this->generateOfferResponse($regular_price, $campaign);
//                }
//            } else {
//                return $this->generateOfferResponse($regular_price, $campaign);
//            }
//        }
//
//        return null;
//    }
//
//    private function generateOfferResponse($regular_price, $campaign)
//    {
//        $offer_price = getPrice($regular_price, $campaign->discount_amount, $campaign->discount_type);
//
//        return [
//            "status" => true,
//            "message" => "Success",
//            "offer_price" => $offer_price ?? null
//        ];
//    }

}
