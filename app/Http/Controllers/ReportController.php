<?php

namespace App\Http\Controllers;

use App\Http\Traits\ActivityLogTraits;
use App\Models\AddonsOrder;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DemoReport;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Orderitem;
use App\Models\Product;
use App\Models\ProductTransfer;
use App\Models\Review;
use App\Models\SMSLogger;
use App\Models\Staff;
use App\Models\Store;
use App\Models\Superstaff;
use App\Models\SuperstaffSalesCommissionBalance;
use App\Models\Toptool;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ReportController extends Controller
{
    use ActivityLogTraits;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $urls = "report";
        $userData = getUserData();
        $store_id = $userData['store_id'];
        $customer_id = $userData['customer_id'];
        $user = $userData['user_id'];

        $toptool = Toptool::where('name', 'Report')->where('uid', $user)->where('store_id', $store_id)->first();

        if (isset($toptool)) {
            $toptool->count = $toptool->count + 1;
            $toptool->save();
        } else {
            $toptool = new Toptool();
            $toptool->name = "Report";
            $toptool->image = "reviews.png";
            $toptool->url = "/report";
            $toptool->count = "1";
            $toptool->uid = $user;
            $toptool->store_id = $store_id;
            $toptool->customer_id = $customer_id;
            $toptool->creator = $user;
            $toptool->editor = $user;
            $toptool->save();
        }
        $activity = " Access Report Page ";
        $this->saveactivity($activity);

        $categories = ExpenseCategory::where("store_id", $store_id)->get();

        return view('admin.report.index', [
            'categories' => $categories,
        ]);
    }


    public function posReport()
    {
        $urls = "pos-report";
        $userData = getUserData();
        $store_id = $userData['store_id'];
        $customer_id = $userData['customer_id'];
        $user = $userData['user_id'];

        $toptool = Toptool::where('name', 'Report')->where('uid', $user)->where('store_id', $store_id)->first();

        if (isset($toptool)) {
            $toptool->count = $toptool->count + 1;
            $toptool->save();
        } else {
            $toptool = new Toptool();
            $toptool->name = "Report";
            $toptool->image = "reviews.png";
            $toptool->url = "/report";
            $toptool->count = "1";
            $toptool->uid = $user;
            $toptool->store_id = $store_id;
            $toptool->customer_id = $customer_id;
            $toptool->creator = $user;
            $toptool->editor = $user;
            $toptool->save();
        }
        $activity = " Access Report Page ";
        $this->saveactivity($activity);

        $branches = Branch::where("store_id", $store_id)->get();

        $categories = ExpenseCategory::where("store_id", $store_id)->get();

        return view('admin.report.pos-report', [
            'urls' => $urls,
            'branches' => $branches,
            'categories' => $categories
        ]);
    }

    public function review()
    {
        $urls = "customer";
        $user = Auth::user()->id;
        $user_type = Auth::user()->type;
        if ($user_type == 'admin' || $user_type == 'dropshipper') {
            $customer = Customer::where('uid', $user)->first();
            $store_id = $customer->active_store;
            $customer_id = $customer->id;
        } elseif ($user_type == 'staff') {
            $staff = Staff::where('uid', $user)->first();
            $store_id = $staff->store_id;
            $customer_id = $staff->customer_id;
        }
        $toptool = Toptool::where('name', 'Review')->where('uid', $user)->where('store_id', $store_id)->first();
        if (isset($toptool)) {
            $toptool->count = $toptool->count + 1;
            $toptool->save();
        } else {
            $toptool = new Toptool();
            $toptool->name = "Review";
            $toptool->image = "reviews.png";
            $toptool->url = "/review";
            $toptool->count = "1";
            $toptool->uid = $user;
            $toptool->store_id = $store_id;
            $toptool->customer_id = $customer_id;
            $toptool->creator = $user;
            $toptool->editor = $user;
            $toptool->save();
        }
        // dd($store_id);
        $review = Review::where('store_id', $store_id)->get();
        // dd($review);
        $activity = " Access Review Page ";
        $this->saveactivity($activity);
        return view('admin.review.index')->with('urls', $urls)->with('reviews', $review);
    }

    public function reviewexport(Request $request)
    {
        $date = Carbon::now();
        $user = Auth::user()->id;
        $user_type = Auth::user()->type;
        if ($user_type == "admin" || $user_type == "dropshipper") {
            $customer = Customer::where('uid', $user)->first();
            $store_id = $customer->active_store;
            $customer_id = $customer->id;
        } elseif ($user_type == 'staff') {
            $staff = Staff::where('uid', Auth::user()->id)->first();
            $store_id = $staff->store_id;
            $customer_id = $staff->customer_id;
        }
        $fileName = 'review(' . $date . ').csv';
        $coupon = Review::where('store_id', $store_id)->get();

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = array('Product Id', 'Order Id', 'Name', 'Comment', 'Created_at');

        $callback = function () use ($coupon, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($coupon as $cat) {
                $row['Product Id'] = $cat->product_id;
                $row['Order Id'] = $cat->order_id;
                $row['Name'] = $cat->name;
                $row['Comment'] = $cat->comment;
                $row['Create Date'] = $cat->created_at;

                fputcsv($file, array(
                    $row['Product Id'],
                    $row['Order Id'],
                    $row['Name'],
                    $row['Comment'],
                    $row['Create Date']
                ));
            }

            fclose($file);
        };
        $activity = " Export Review ";
        $this->saveactivity($activity);
        return response()->stream($callback, 200, $headers);
    }

    public function delreview($id)
    {
        $review = Review::find($id);
        $review->delete();
        $activity = " Delete Review " . $id;
        $this->saveactivity($activity);
        Session::flash('message', 'Successfully Review Delete');
        return back();
    }

    /**
     * Display top selling product
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function topselling()
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $urls = "inventory";

        $paginatedProducts = $this->getProductByOrderCount($store_id, "desc");

        return view('admin.product.topselling')
            ->with('urls', $urls)
            ->with('store_id', $store_id)
            ->with('products', $paginatedProducts);
    }

    /**
     * Display low selling product
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function lowestselling()
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $urls = "inventory";

        $paginatedProducts = $this->getProductByOrderCount($store_id);

        return view('admin.product.lowestselling')
            ->with('urls', $urls)
            ->with('store_id', $store_id)
            ->with('products', $paginatedProducts);
    }

    /**
     * Get all product based on order count
     *
     * @param $store_id
     * @param $sortOrder
     * @return \Illuminate\Pagination\LengthAwarePaginator
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    private function getProductByOrderCount($store_id, $sortOrder = "asc")
    {
        // Fetch orders and order items
        $orders = Order::with('orderitems')->where('store_id', $store_id)->get();

        $productCounts = [];
        foreach ($orders as $order) {
            foreach ($order->orderitems as $orderitem) {
                if (isset($productCounts[$orderitem->product_id])) {
                    $productCounts[$orderitem->product_id]++;
                } else {
                    $productCounts[$orderitem->product_id] = 1;
                }
            }
        }

        $products = DB::table('products')
            ->whereIn('id', array_keys($productCounts))
            ->get(); // Fetch all products without pagination for sorting

        // Map product popularity counts and additional data
        foreach ($products as $product) {
            $product->popularity = $productCounts[$product->id] ?? 0; // Add popularity property
            $conversionsCurrency = conversionsCurrency($product->regular_price, $product->currency_id, $store_id);
            $product->converted_price = $conversionsCurrency['symbol'] . $conversionsCurrency['amount'];
        }

        // Convert the collection to an array for sorting
        $productsArray = $products->toArray(); // Converts to array of objects


        // Sort products by popularity
        usort($productsArray, function ($a, $b) use ($sortOrder) {
            if ($sortOrder === 'asc') {
                return $a->popularity <=> $b->popularity; // Ascending
            } else {
                return $b->popularity <=> $a->popularity; // Descending
            }
        });

        // Paginate sorted products manually
        $page = request()->get('page', 1); // Current page
        $perPage = 20; // Items per page
        $offset = ($page - 1) * $perPage;
        $paginatedProducts = array_slice($productsArray, $offset, $perPage);

        // Create a new LengthAwarePaginator
        $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedProducts,
            count($productsArray),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paginatedProducts;
    }


    public function completeorder(Request $request)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $from_date = $request->from_date ? Carbon::parse($request->from_date) : null;
        $to_date = $request->to_date ? Carbon::parse($request->to_date) : null;
        $search = $request->search;

        $data['from_date'] = $request->from_date;
        $data['to_date'] = $request->to_date;
        $data['search'] = $search;

        $data['urls'] = "report";

        $ordersQuery = Order::where('status', 'Delivered')->where('store_id', $store_id);

        $ordersQuery->where(function ($query) use ($from_date, $to_date) {
            if ($from_date && !$to_date) {
                $query->where('created_at', '>=', $from_date->startOfDay());
            } elseif (!$from_date && $to_date) {
                $query->where('created_at', '<=', $to_date->endOfDay());
            } elseif ($from_date && $to_date) {
                $query->whereBetween('created_at', [$from_date->startOfDay(), $to_date->endOfDay()]);
            }
        });

        // Search logic
        if (!empty($search)) {
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('reference_no', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('subtotal', 'like', "%$search%")
                    ->orWhere('discount', 'like', "%$search%")
                    ->orWhere('shipping', 'like', "%$search%")
                    ->orWhere('tax', 'like', "%$search%")
                    ->orWhere('total', 'like', "%$search%");
            });
        }

        $ordersQuery->orderBy('id', 'DESC');

        // Calculate the sum of 'total' for all results (optional, for reporting purposes)
        $data['revenue'] = $ordersQuery->get()->sum('total') ?? 0;

        // Exclude specific plan IDs and group by user
        $orders = $ordersQuery->paginate(20);
        $data['orders'] = $orders;

        // Calculate the sum of 'total' for the current page's addonsOrders
        $data['pageTotalAmount'] = $orders->getCollection()->sum('total') ?? 0;

        return view('admin.product.completeorder', $data);
    }

    public function rejectorder()
    {
        $user = Auth::user()->id;
        $user_type = Auth::user()->type;
        if ($user_type == "admin" || $user_type == "dropshipper") {
            $customer = Customer::where('uid', $user)->first();
            $store_id = $customer->active_store;
            $customer_id = $customer->id;
        } elseif ($user_type == 'staff') {
            $staff = Staff::where('uid', Auth::user()->id)->first();
            $store_id = $staff->store_id;
            $customer_id = $staff->customer_id;
        }
        $urls = "inventory";
        $orders = Order::where('status', 'Cancel')->where('store_id', $store_id)->orderBy('id', 'DESC')->get();
        return view('admin.product.rejectorder')->with('urls', $urls)->with('orders', $orders);
    }

    public function changereviewssstatus(Request $request)
    {
        if ($request->text2 == '') {
            Session::flash('message', 'Please Select Review');
            return back();
        }
        if ($request->action == 'select') {
            Session::flash('message', 'Please Select a Option');
            return back();
        }
        if ($request->action == 'delete') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Review::find($ids);
                    $product->delete();
                }
            }
            $activity = " Delete Customer ";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Deleted Review');
            return back();
        }
    }

    public function clientSalesReport()
    {
        if (Auth::user()->phone == "01677515573") {
            $data = DemoReport::first();

            return view('superadmin.demo-report', [
                "total_admin" => $data->total_admin ?? 0,
                "total_paid_store" => $data->total_paid_store ?? 0,
                "total_dropshipper" => $data->total_dropshipper ?? 0,
                "total_paid_dropshipper" => $data->total_paid_dropshipper ?? 0,
                "total_customer" => $data->total_customer ?? 0,
                "total_affiliate" => $data->total_affiliate ?? 0,
                "total_customer_affiliate" => $data->total_customer_affiliate ?? 0,
                "lifetime_total_paid_store" => $data->lifetime_total_paid_store ?? 0,
                "lifetime_total_paid_dropshipper" => $data->lifetime_total_paid_dropshipper ?? 0,
                "total_new_sell_amount_monthly" => $data->total_new_sell_amount_monthly ?? 0,
                "total_new_sell_amount_yearly" => $data->total_new_sell_amount_yearly ?? 0,
                "total_renew_sell_amount_monthly" => $data->total_renew_sell_amount_monthly ?? 0,
                "total_renew_sell_amount_yearly" => $data->total_renew_sell_amount_yearly ?? 0,
                "total_addon_amount_monthly" => $data->total_addon_amount_monthly ?? 0,
                "total_addon_amount_yearly" => $data->total_addon_amount_yearly ?? 0,
                "total_addon_amount_without_domain_monthly" => $data->total_addon_amount_without_domain_monthly ?? 0,
                "total_addon_amount_without_domain_yearly" => $data->total_addon_amount_without_domain_yearly ?? 0,
                "total_package_amount_monthly" => $data->total_package_amount_monthly ?? 0,
                "total_package_amount_yearly" => $data->total_package_amount_yearly ?? 0,
                "total_revenue_monthly" => $data->total_revenue_monthly ?? 0,
                "total_revenue_yearly" => $data->total_revenue_yearly ?? 0,
                "total_revenue_after_discount_monthly" => $data->total_revenue_after_discount_monthly ?? ($data->total_revenue_monthly ?? 0),
                "total_revenue_after_discount_yearly" => $data->total_revenue_after_discount_yearly ?? ($data->total_revenue_yearly ?? 0),
                "total_due_monthly" => $data->total_due_monthly ?? 0,
                "total_due_yearly" => $data->total_due_yearly ?? 0,
            ]);
        }

        // Get current year and month
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        $addonsOrders = AddonsOrder::with("store")
            ->where('status', 'Complete')
            ->whereYear('created_at', $currentYear)
            ->get();

        $addonMonthlyTotal = 0;
        $addonYearlyTotal = 0;
        $packageMonthlyTotal = 0;
        $packageYearlyTotal = 0;
        $addonMonthlyExcludingDomain = 0;
        $addonYearlyExcludingDomain = 0;

        $totalMonthlyRevenew = 0;
        $totalYearlyRevenew = 0;
        $totalDueMonthly = 0;
        $totalDueYearly = 0;

        $totalNewCustomerPackageMonthly = 0;
        $totalNewCustomerPackageYearly = 0;
        $totalRenewCustomerPackageMonthly = 0;
        $totalRenewCustomerPackageYearly = 0;

        foreach ($addonsOrders as $order) {
            $addons = $order->addons;
            if (is_string($addons)) {
                $addons = json_decode($addons, true); // Decode the JSON string to array
            }

            if (is_array($addons)) {
                foreach ($addons as $addon) {
                    $addonPrice = isset($addon['price']) ? (float)$addon['price'] : 0;
                    $addonTitle = $addon['title'] ?? '';

                    // Add to yearly addon total
                    $addonYearlyTotal += $addonPrice;

                    // Add to monthly addon total if created in the current month
                    if (Carbon::parse($order->created_at)->month === $currentMonth) {
                        $addonMonthlyTotal += $addonPrice;
                    }

                    // Exclude domain addons
                    if (stripos($addonTitle, 'domain') === false) {
                        $addonYearlyExcludingDomain += $addonPrice;

                        if (Carbon::parse($order->created_at)->month === $currentMonth) {
                            $addonMonthlyExcludingDomain += $addonPrice;
                        }
                    }
                }
            }

            $package = $order->package;
            if (is_string($package)) {
                $package = json_decode($package, true); // Decode the JSON string to array
            }

            if (is_array($package) && isset($package['offerprice'])) {
                $packagePrice = (float)$package['offerprice'];

                // Add to monthly package total if created in the current month
                if (Carbon::parse($order->created_at)->month === $currentMonth) {
                    $packageMonthlyTotal += $packagePrice;
                }

                // Add to yearly package total
                if (Carbon::parse($order->created_at)->year === $currentYear) {
                    $packageYearlyTotal += $packagePrice;
                }

                // Monthly
                if (Carbon::parse($order->created_at)->month === $currentMonth) {
                    // New package data
                    if (!is_null($order->store) && Carbon::parse($order->store->purchase_date)->month === $currentMonth && is_null($order->store->renew_date)) {
                        $totalNewCustomerPackageMonthly += $packagePrice;
                    }

                    // Renew package data
                    if (!is_null($order->store) && !is_null($order->store->renew_date) && Carbon::parse($order->store->renew_date)->month === $currentMonth) {
                        $totalRenewCustomerPackageMonthly += $packagePrice;
                    }

                }

                //Yearly
                if (Carbon::parse($order->created_at)->year === $currentYear) {
                    // New package data
                    if (!is_null($order->store) && Carbon::parse($order->store->purchase_date)->month === Carbon::parse($order->created_at)->month) {
                        $totalNewCustomerPackageYearly += $packagePrice;
                    }

                    // Renew package data
                    if (!is_null($order->store) && !is_null($order->store->renew_date) && Carbon::parse($order->store->renew_date)->year === $currentYear) {
                        $totalRenewCustomerPackageYearly += $packagePrice;
                    }
                }

            }

            // Add to yearly total amount
            $totalYearlyRevenew += $order->total ?? 0;
            $totalDueYearly += (float) ($order->due_amount ?? 0);

            // Add to monthly addon total if created in the current month
            if (Carbon::parse($order->created_at)->month === $currentMonth) {
                $totalMonthlyRevenew += $order->total ?? 0;
                $totalDueMonthly += (float) ($order->due_amount ?? 0);
            }
        }

        $totalAmountMonthly = (int)$addonMonthlyTotal + (int)$packageMonthlyTotal;
        $totalAmountYearly = (int)$addonYearlyTotal + (int)$packageYearlyTotal;


        $Staffs = Superstaff::with(['sales' => function ($query) {
            $query->select('id', 'staff_id', 'isSetup', 'isNew', 'isRenew', 'commission_amount', 'created_at');
        }])->where("role_id", 11)
            ->withCount([
                // Monthly new sales count
                'sales as monthly_new_sell_count' => function ($query) {
                    $query->where('isNew', 1)
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                },
                // Yearly new sales count
                'sales as yearly_new_sell_count' => function ($query) {
                    $query->where('isNew', 1)
                        ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                },
                // Monthly renewal sales count
                'sales as monthly_renew_sell_count' => function ($query) {
                    $query->where('isRenew', 1)
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                },
                // Yearly renewal sales count
                'sales as yearly_renew_sell_count' => function ($query) {
                    $query->where('isRenew', 1)
                        ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                },
                // Monthly setup sales count
                'sales as monthly_setup_count' => function ($query) {
                    $query->where('isSetup', 1)
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                },
                // Yearly setup sales count
                'sales as yearly_setup_count' => function ($query) {
                    $query->where('isSetup', 1)
                        ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                },
            ])
            ->addSelect([
                // Monthly new sales total commission
                'monthly_new_sell_total' => SuperstaffSalesCommissionBalance::selectRaw('SUM(total_amount)')
                    ->whereColumn('staff_id', 'superstaffs.id')
                    ->where('isNew', 1)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),

                // Yearly new sales total commission
                'yearly_new_sell_total' => SuperstaffSalesCommissionBalance::selectRaw('SUM(total_amount)')
                    ->whereColumn('staff_id', 'superstaffs.id')
                    ->where('isNew', 1)
                    ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),

                // Monthly renewal sales total commission
                'monthly_renew_sell_total' => SuperstaffSalesCommissionBalance::selectRaw('SUM(total_amount)')
                    ->whereColumn('staff_id', 'superstaffs.id')
                    ->where('isRenew', 1)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),

                // Yearly renewal sales total commission
                'yearly_renew_sell_total' => SuperstaffSalesCommissionBalance::selectRaw('SUM(total_amount)')
                    ->whereColumn('staff_id', 'superstaffs.id')
                    ->where('isRenew', 1)
                    ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),

                // Monthly setup sales total commission
                'monthly_setup_total' => SuperstaffSalesCommissionBalance::selectRaw('SUM(total_amount)')
                    ->whereColumn('staff_id', 'superstaffs.id')
                    ->where('isSetup', 1)
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),

                // Yearly setup sales total commission
                'yearly_setup_total' => SuperstaffSalesCommissionBalance::selectRaw('SUM(total_amount)')
                    ->whereColumn('staff_id', 'superstaffs.id')
                    ->where('isSetup', 1)
                    ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
            ])
            ->get();

        return view('superadmin.report', [
            "addonMonthlyTotal" => $addonMonthlyTotal,
            "addonYearlyTotal" => $addonYearlyTotal,
            "packageMonthlyTotal" => $packageMonthlyTotal,
            "packageYearlyTotal" => $packageYearlyTotal,
            "addonMonthlyExcludingDomain" => $addonMonthlyExcludingDomain,
            "addonYearlyExcludingDomain" => $addonYearlyExcludingDomain,
            "totalMonthlyRevenew" => $totalMonthlyRevenew,
            "totalYearlyRevenew" => $totalYearlyRevenew,
            "totalAmountMonthly" => $totalAmountMonthly,
            "totalAmountYearly" => $totalAmountYearly,
            "totalRevenueAfterDiscountMonthly" => $totalMonthlyRevenew,
            "totalRevenueAfterDiscountYearly" => $totalYearlyRevenew,
            "totalDueMonthly" => $totalDueMonthly,
            "totalDueYearly" => $totalDueYearly,
            "totalNewCustomerPackageMonthly" => (int)$totalNewCustomerPackageMonthly,
            "totalNewCustomerPackageYearly" => (int)$totalNewCustomerPackageYearly,
            "totalRenewCustomerPackageMonthly" => (int)$totalRenewCustomerPackageMonthly,
            "totalRenewCustomerPackageYearly" => (int)$totalRenewCustomerPackageYearly,
            "Staffs" => $Staffs,
        ]);
    }

    public function adminReport($section)
    {
        try {
            if (empty($section)) {
                return response()->json(['status' => false, 'message' => 'Section name is required']);
            }

            $userData = getUserData();
            $storeId = $userData['store_id'];
            $customerId = $userData['customer_id'];

            // Single optimized query for all dashboard data
            if ($section === 'dashboardData') {
                $data = $this->getDashboardData($storeId, $customerId);
                return sendResponse("Success", $data);
            }

            // Single optimized query for all dashboard data
            if ($section === 'posDashboardData') {
                $data = $this->getPOSDashboardData($storeId, $customerId);
                return sendResponse("Success", $data);
            }

            // Fallback for legacy requests
            switch ($section) {
                case 'generalCount':
                    return sendResponse("Success", $this->getGeneralCountData($storeId));
                case 'orderData':
                    return sendResponse("Success", $this->getOrderData($storeId, $customerId));
                default:
                    return sendResponse("Data not found!", '');
            }

        } catch (\Exception $exception) {
            return serverError();
        }
    }

    protected function getDashboardData($storeId, $customerId)
    {
        // Get all data in minimal queries
        return [
            'general' => $this->getGeneralCountData($storeId),
            'orders' => $this->getOrderData($storeId, $customerId),
//            'expenses' => $this->getExpenseData($storeId) // Optional if you add expense tracking
        ];
    }

    protected function getPOSDashboardData($storeId, $customerId)
    {
        // Get all data in minimal queries
        return [
            'general' => $this->getPOSGeneralCountData($storeId),
            'orders' => $this->getPOSOrderData($storeId, $customerId),
//            'expenses' => $this->getExpenseData($storeId) // Optional if you add expense tracking
        ];
    }

    protected function getGeneralCountData($storeId)
    {
        // Single query for product counts
        $productCounts = DB::table('products')
            ->where('store_id', $storeId)
            ->selectRaw('
            SUM(CASE WHEN status IN ("active", "inactive") THEN 1 ELSE 0 END) as total,
            SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) as inactive
        ')
            ->first();

        // Single query for user counts
        $userCounts = DB::table('users')
            ->where('store_id', $storeId)
            ->selectRaw('
            SUM(CASE WHEN type IN ("customer", "customerAffiliate") THEN 1 ELSE 0 END) as total,
            SUM(CASE WHEN type = "customer" THEN 1 ELSE 0 END) as customers,
            SUM(CASE WHEN type = "customerAffiliate" THEN 1 ELSE 0 END) as affiliates
        ')->first();

        return [
            'totalProduct' => $productCounts->total ?? 0,
            'activeProduct' => $productCounts->active ?? 0,
            'inactiveProduct' => $productCounts->inactive ?? 0,
            'totalUser' => $userCounts->total ?? 0,
            'totalCustomer' => $userCounts->customers ?? 0,
            'totalCustomerAffiliate' => $userCounts->affiliates ?? 0
        ];
    }

    protected function getOrderData($storeId, $customerId)
    {
        // Single optimized query for all order statistics
        $orderStats = DB::table('orders')
            ->where('store_id', $storeId)
            ->selectRaw('
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = "Delivered" THEN total ELSE 0 END) as total_sell,
            SUM(CASE WHEN status = "Delivered" THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status IN ("Pending", "On Hold", "Processing", "Shipping") THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ("Cancelled", "Returned", "Payment Failed", "Restock") THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = "Delivered" AND branch_id IS NULL THEN total ELSE 0 END) as website_revenue,
            SUM(CASE WHEN status = "Delivered" AND branch_id IS NOT NULL AND customer_id = ? THEN total ELSE 0 END) as branch_revenue
        ', [$customerId])
            ->first();

        // Get cost in same query using join
        $costData = DB::table('orders')
            ->join('orderitems', 'orders.id', '=', 'orderitems.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'Delivered')
            ->selectRaw('SUM(orderitems.cost * orderitems.quantity) as total_cost')
            ->first();

        return [
            'totalOrder' => $orderStats->total_orders ?? 0,
            'deliveredOrder' => (int)$orderStats->delivered ?? 0,
            'pendingOrder' => (int)$orderStats->pending ?? 0,
            'cancelOrder' => (int)$orderStats->cancelled ?? 0,
        ];
    }

    protected function getPOSGeneralCountData($storeId)
    {
        $productCounts = DB::table('products')
            ->where('store_id', $storeId)
            ->whereIn('id', function ($query) use ($storeId) {
                $query->select('product_id')
                    ->distinct()
                    ->from('branchproducts')
                    ->whereIn('branch_id', function ($query) use ($storeId) {
                        $query->select('id')
                            ->from('branches')
                            ->where('store_id', $storeId);
                    });
            })
            ->selectRaw('
                SUM(CASE WHEN status IN ("active", "inactive") THEN 1 ELSE 0 END) as total,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) as inactive
            ')
            ->first();

        // Single query for user counts
        $userCounts = DB::table('users')
            ->whereIn('id', function ($query) use ($storeId) {
                $query->select('uid')
                    ->distinct()
                    ->from('orders')
                    ->where('store_id', $storeId)
                    ->whereIn('branch_id', function ($query) use ($storeId) {
                        $query->select('id')
                            ->from('branches')
                            ->where('store_id', $storeId);
                    });
            })
            ->selectRaw('COUNT(DISTINCT users.id) as total')
            ->first();

        return [
            'totalProduct' => $productCounts->total ?? 0,
            'activeProduct' => $productCounts->active ?? 0,
            'inactiveProduct' => $productCounts->inactive ?? 0,
            'totalUser' => $userCounts->total ?? 0,
        ];
    }

    protected function getPOSOrderData($storeId, $customerId)
    {
        // Single optimized query for all order statistics
        $orderStats = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereIn('branch_id', function ($query) use ($storeId) {
                $query->select('id')
                    ->from('branches')
                    ->where('store_id', $storeId);
            })
            ->selectRaw('
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = "Delivered" THEN total ELSE 0 END) as total_sell,
            SUM(CASE WHEN status = "Delivered" THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status IN ("Pending", "On Hold", "Processing", "Shipping") THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status IN ("Cancelled", "Returned", "Payment Failed", "Restock") THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = "Delivered" AND branch_id IS NULL THEN total ELSE 0 END) as website_revenue,
            SUM(CASE WHEN status = "Delivered" AND branch_id IS NOT NULL AND customer_id = ? THEN total ELSE 0 END) as branch_revenue
        ', [$customerId])
            ->first();

        return [
            'totalOrder' => $orderStats->total_orders ?? 0,
            'deliveredOrder' => (int)$orderStats->delivered ?? 0,
            'pendingOrder' => (int)$orderStats->pending ?? 0,
            'cancelOrder' => (int)$orderStats->cancelled ?? 0,
        ];
    }

    public function storeSmsList(Request $request)
    {
        if (canSuperStaffAccess('paid_clients')) {
            $from_date = $request->from_date ? Carbon::parse($request->from_date) : null;
            $to_date = $request->to_date ? Carbon::parse($request->to_date) : null;
            $search = $request->search;
            $type = $request->type;

            $data['from_date'] = $request->from_date;
            $data['to_date'] = $request->to_date;
            $data['type'] = $type;
            $data['search'] = $search;

            // Group by store and count SMS
            $smsQuery = SMSLogger::with('store')
                ->selectRaw("
                    *,
                    IFNULL(store_id, 'system_sms') as store_group,
                    COUNT(*) as sms_count
                ")
                ->groupBy('store_group');

            // Filter by type
            if ($type != "") {
                $smsQuery->where('user_type', $type);
            }

            // Search logic
            if (!empty($search)) {
                $smsQuery->where(function ($query) use ($search) {
                    $query->where('purpose', 'LIKE', "%$search%")
                        ->orWhere('phone', 'LIKE', "%$search%")
                        ->orWhereHas('store', function ($subQuery) use ($search) {
                            $subQuery->where('name', 'LIKE', "%$search%")
                                ->orWhere('url', 'LIKE', "%$search%")
                                ->orWhereHas('user', function ($subSubQuery) use ($search) {
                                    $subSubQuery->where('id', $search)
                                        ->orWhere('name', 'LIKE', "%$search%")
                                        ->orWhere('phone', 'LIKE', "%$search%")
                                        ->orWhere('email', 'LIKE', "%$search%");
                                });
                        });
                });
            }

            if ($from_date && !$to_date) {
                $smsQuery->where('created_at', '>=', $from_date->startOfDay());
            } elseif (!$from_date && $to_date) {
                $smsQuery->where('created_at', '<=', $to_date->endOfDay());
            } elseif ($from_date && $to_date) {
                $smsQuery->whereBetween('created_at', [$from_date->startOfDay(), $to_date->endOfDay()]);
            }

            $data['smsList'] = $smsQuery->orderBy("store_group", "DESC")->paginate(20);

            return view('superadmin.store-sms-list', $data);
        } else {
            return redirect()->route('superadmin.index');
        }
    }

    public function smsLogReport(Request $request, $id = null)
    {
        if (canSuperStaffAccess('paid_clients')) {
            $from_date = $request->from_date ? Carbon::parse($request->from_date) : null;
            $to_date = $request->to_date ? Carbon::parse($request->to_date) : null;

            $data['from_date'] = $request->from_date;
            $data['to_date'] = $request->to_date;
            $data['store_id'] = $id;

            $clientQuery = SMSLogger::with('store')->where('store_id', $id);
            if ($from_date && !$to_date) {
                $clientQuery->where('created_at', '>=', $from_date->startOfDay());
            } elseif (!$from_date && $to_date) {
                $clientQuery->where('created_at', '<=', $to_date->endOfDay());
            } elseif ($from_date && $to_date) {
                $clientQuery->whereBetween('created_at', [$from_date->startOfDay(), $to_date->endOfDay()]);
            }

            $data['smsList'] = $clientQuery->orderBy("id", "DESC")->paginate(20);

            return view('superadmin.sms-log-list', $data);
        } else {
            return redirect()->route('superadmin.index');
        }
    }

    protected function calculateStats($query)
    {
        return [
            'gross_sale' => $query->sum('subtotal'),
            'total_discount' => $query->sum('discount') + $query->sum('extradiscount'),
            'net_sale' => $query->sum('total') - $query->sum('shipping') - $query->sum('tax'),
            'shipping_charge' => $query->sum('shipping'),
            'total_taxes' => $query->sum('tax'),
            'total_sale' => $query->sum('total'),
            'order_count' => $query->count()
        ];
    }

    public function revenueReport(Request $request)
    {
        // Get date range from request or use default (current month)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $userData = getUserData();
        $storeId = $userData['store_id'];

        // Base query for ALL orders (regardless of status)
        $allOrdersQuery = Order::whereBetween('created_at', [$startDate, $endDate]);

        // Query for only delivered orders (maintain existing functionality)
        $deliveredOrdersQuery = (clone $allOrdersQuery)->where('status', 'Delivered');

        if ($storeId) {
            $allOrdersQuery->where('store_id', $storeId);
            $deliveredOrdersQuery->where('store_id', $storeId);
        }

        // Calculate website revenue (branch_id is null)
        $websiteAllOrders = (clone $allOrdersQuery)->whereNull('branch_id');
        $websiteDeliveredOrders = (clone $deliveredOrdersQuery)->whereNull('branch_id');

        // Calculate POS revenue (branch_id is not null)
        $posAllOrders = (clone $allOrdersQuery)->whereNotNull('branch_id');
        $posDeliveredOrders = (clone $deliveredOrdersQuery)->whereNotNull('branch_id');

        // Calculate stats for all orders
        $allWebsiteStats = $this->calculateStats($websiteAllOrders);
        $allPosStats = $this->calculateStats($posAllOrders);
        $allTotalStats = $this->sumStats($allWebsiteStats, $allPosStats);

        // Calculate stats for delivered orders (existing functionality)
        $deliveredWebsiteStats = $this->calculateStats($websiteDeliveredOrders);
        $deliveredPosStats = $this->calculateStats($posDeliveredOrders);
        $deliveredTotalStats = $this->sumStats($deliveredWebsiteStats, $deliveredPosStats);

        return response()->json([
            'all_orders' => [
                'website' => $allWebsiteStats,
                'pos' => $allPosStats,
                'total' => $allTotalStats
            ],
            'delivered_orders' => [
                'website' => $deliveredWebsiteStats,
                'pos' => $deliveredPosStats,
                'total' => $deliveredTotalStats
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'order_counts' => [
                'total' => $allOrdersQuery->count(),
                'delivered' => $deliveredOrdersQuery->count(),
                'by_status' => $this->getOrderCountsByStatus($allOrdersQuery)
            ]
        ]);
    }

    public function posRevenueReport(Request $request)
    {
        // Get date range from request or use default (current month)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());
        $branchId = $request->input('branchId', NULL);

        $userData = getUserData();
        $storeId = $userData['store_id'];

        // Base query for ALL orders (regardless of status)
        $allOrdersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('branch_id', $branchId);

        // Query for only delivered orders (maintain existing functionality)
        $deliveredOrdersQuery = (clone $allOrdersQuery)->where('status', 'Delivered');

        if ($storeId) {
            $allOrdersQuery->where('store_id', $storeId);
            $deliveredOrdersQuery->where('store_id', $storeId);
        }

        // Calculate stats for the branch
        $branchAllStats = $this->calculateStats($allOrdersQuery);
        $branchDeliveredStats = $this->calculateStats($deliveredOrdersQuery);

        // Get order status breakdown for the branch
        $statusBreakdown = $this->getOrderCountsByStatus($allOrdersQuery);

        return response()->json([
            'branch' => [
                'all_orders' => $branchAllStats,
                'delivered_orders' => $branchDeliveredStats
            ],
            'branchInfo' => [
                'general' => $this->getPOSBranchGeneralCountData($branchId),
                'total_order' => $this->getPOSBranchOrderData($branchId),
            ],
            'order_counts' => [
                'total' => $allOrdersQuery->count(),
                'delivered' => $deliveredOrdersQuery->count(),
                'by_status' => $statusBreakdown
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'branch_id' => $branchId
        ]);
    }

    protected function getPOSBranchGeneralCountData($branchId)
    {
        $userData = getUserData();
        $storeId = $userData['store_id'];

        $productCounts = DB::table('products')
            ->where('store_id', $storeId)
            ->whereIn('id', function ($query) use ($branchId) {
                $query->select('product_id')
                    ->distinct()
                    ->from('branchproducts')
                    ->where('branch_id', $branchId);
            })
            ->selectRaw('
                SUM(CASE WHEN status IN ("active", "inactive") THEN 1 ELSE 0 END) as total
            ')
            ->first();

        // Single query for user counts
        $userCounts = DB::table('users')
            ->whereIn('id', function ($query) use ($storeId, $branchId) {
                $query->select('uid')
                    ->distinct()
                    ->from('orders')
                    ->where('store_id', $storeId)
                    ->where('branch_id', $branchId);
            })
            ->selectRaw('COUNT(DISTINCT users.id) as total')
            ->first();

        return [
            'total_product' => (int)($productCounts->total ?? 0),
            'total_user' => (int)($userCounts->total ?? 0),
        ];
    }

    protected function getPOSBranchOrderData($branchId)
    {
        $userData = getUserData();
        $storeId = $userData['store_id'];

        // Single optimized query for all order statistics
        $orderStats = DB::table('orders')
            ->where('store_id', $storeId)
            ->where('branch_id', $branchId)
            ->selectRaw('COUNT(*) as total_orders')
            ->first();

        return (int)($orderStats->total_orders ?? 0);
    }


    protected function sumStats($stats1, $stats2)
    {
        return [
            'gross_sale' => $stats1['gross_sale'] + $stats2['gross_sale'],
            'total_discount' => $stats1['total_discount'] + $stats2['total_discount'],
            'net_sale' => $stats1['net_sale'] + $stats2['net_sale'],
            'shipping_charge' => $stats1['shipping_charge'] + $stats2['shipping_charge'],
            'total_taxes' => $stats1['total_taxes'] + $stats2['total_taxes'],
            'total_sale' => $stats1['total_sale'] + $stats2['total_sale'],
            'order_count' => $stats1['order_count'] + $stats2['order_count']
        ];
    }

    protected function getOrderCountsByStatus($query)
    {
        return $query->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }


    public function productTransferReport(Request $request)
    {
        $userData = getUserData();
        $storeId = $userData['store_id'];

        $from_date = $request->from_date ? Carbon::parse($request->from_date) : null;
        $to_date = $request->to_date ? Carbon::parse($request->to_date) : null;
        $search = $request->search;

        $data['from_date'] = $request->from_date;
        $data['to_date'] = $request->to_date;
        $data['search'] = $search;

        $clientQuery = ProductTransfer::query();

        $clientQuery->with(['product', 'branchFrom', 'branchTo'])->where('store_id', $storeId);

        if ($from_date && !$to_date) {
            $clientQuery->where('created_at', '>=', $from_date->startOfDay());
        } elseif (!$from_date && $to_date) {
            $clientQuery->where('created_at', '<=', $to_date->endOfDay());
        } elseif ($from_date && $to_date) {
            $clientQuery->whereBetween('created_at', [$from_date->startOfDay(), $to_date->endOfDay()]);
        }

        // Search logic
        if (!empty($search)) {
            $clientQuery->where(function ($query) use ($search) {
                $query->where('transfer_qty', $search)
                    ->orWhere('old_qty', $search)
                    ->orWhereHas('product', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%$search%");
                    })->orWhereHas('branchFrom', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%$search%");
                    })->orWhereHas('branchTo', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', "%$search%");
                    });
            });
        }

        $data['products'] = $clientQuery->orderBy("id", "desc")->paginate(20);

        return view('admin.report.product-transfer-report', $data);
    }


}
