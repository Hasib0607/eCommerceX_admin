<?php

use App\Http\Controllers\Api\v2\BookingController;
use App\Http\Controllers\Api\v2\EbitansAnalytics\EbtAnalyticsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\Api\v2\LoginController;
use App\Http\Controllers\Api\v2\SubdomainController;
use App\Http\Controllers\Api\v2\OrderController;
use App\Http\Controllers\Api\v2\UserController;
use App\Http\Controllers\Api\v2\PaymentPageController;
use App\Http\Controllers\Api\v2\ImageController;
use App\Http\Controllers\Api\v2\AdminBlogController;
use App\Http\Controllers\Api\v2\AnnouncementController;
use App\Http\Controllers\Api\v2\Marketplace\MarketplaceController;
use App\Http\Controllers\PaymentGateway\BkashController;
use App\Http\Controllers\Api\v2\NewsLetterController;
use App\Http\Controllers\Api\v2\PosController;
use App\Http\Controllers\Api\v2\ThemeController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\PaymentGateway\AdminBkashController;
use App\Http\Controllers\QuickLoginController;
use App\Http\Controllers\Api\v2\PseAdsController;
use App\Http\Controllers\Api\v2\ProductAffiliateController;
use App\Http\Controllers\Api\v2\DistrictController;
use App\Http\Controllers\Api\v2\CountryController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\PaymentGateway\MarchantPaymentGetwayKYCController;
use App\Http\Controllers\Api\v2\AdminVisitorController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\Courier\CourierController;
use App\Http\Controllers\Api\v2\CartController;
use App\Http\Controllers\Api\v2\MarketingController;
use App\Http\Controllers\WhatsAppAutomation\AuthController;
use App\Http\Controllers\WhatsAppAutomation\DashboardController;
use App\Http\Controllers\WhatsAppAutomation\LeadController;
use App\Http\Controllers\WhatsAppAutomation\HandoffController;
use App\Http\Controllers\WhatsAppAutomation\RealtimeController;
use App\Http\Controllers\WhatsAppAutomation\ReviewController;
use App\Http\Controllers\WhatsAppAutomation\LearningController;
use App\Http\Controllers\WhatsAppAutomation\CampaignController;
use App\Http\Controllers\WhatsAppAutomation\KnowledgeController;
use App\Http\Controllers\WhatsAppAutomation\OutboundController;
use App\Http\Controllers\WhatsAppAutomation\AnalyticsController;
use App\Http\Controllers\WhatsAppAutomation\TrainingController;
use App\Http\Controllers\WhatsAppAutomation\CohortController;
use App\Http\Controllers\WhatsAppAutomation\LiveClientShowcaseController;
use App\Http\Controllers\WhatsAppAutomation\GatewaySessionController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('whatsapp')->group(function () {
    Route::post('/auth/verify', [AuthController::class, 'verify']);

    Route::middleware(['whatsapp.react'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::post('/gateway/sessions/create', [GatewaySessionController::class, 'create']);
        Route::get('/gateway/sessions/{tenantId}/status', [GatewaySessionController::class, 'status']);
        Route::get('/gateway/sessions/{tenantId}/qr', [GatewaySessionController::class, 'qr']);
        Route::match(['get', 'post', 'delete'], '/gateway/sessions/{tenantId}/logout', [GatewaySessionController::class, 'logout']);
        Route::get('/realtime/stream', [RealtimeController::class, 'stream']);
        Route::get('/realtime/events', [RealtimeController::class, 'events']);
        Route::get('/leads', [LeadController::class, 'index']);
        Route::get('/leads/{sessionId}', [LeadController::class, 'show']);
        Route::post('/leads/{sessionId}/status', [LeadController::class, 'updateStatus']);
        Route::post('/leads/{sessionId}/auto-reply', [LeadController::class, 'updateAutoReply']);
        Route::post('/leads/{sessionId}/promised-payment', [LeadController::class, 'updatePromisedPayment']);
        Route::get('/leads/{sessionId}/tags', [LeadController::class, 'tags']);
        Route::post('/leads/{sessionId}/tags', [LeadController::class, 'assignTag']);
        Route::delete('/leads/{sessionId}/tags/{tagName}', [LeadController::class, 'removeTag']);
        Route::post('/leads/{sessionId}/tags/refresh', [LeadController::class, 'refreshTags']);
        Route::get('/leads/{sessionId}/history', [LeadController::class, 'history']);
        Route::get('/leads/{sessionId}/followup-plans', [LeadController::class, 'followupPlans']);
        Route::post('/leads/{sessionId}/followup-plans', [LeadController::class, 'createFollowupPlan']);
        Route::get('/tags', [LeadController::class, 'allTags']);
        Route::post('/tags', [LeadController::class, 'createTag']);
        Route::get('/followup-plans', [LeadController::class, 'allFollowupPlans']);
        Route::get('/followup-plans/reasons', [LeadController::class, 'followupPlanReasons']);
        Route::post('/followup-plans/scheduler/run', [LeadController::class, 'runFollowupScheduler']);
        Route::post('/followup-plans/{id}/status', [LeadController::class, 'updateFollowupPlanStatus']);
        Route::post('/followup-plans/{id}/mark-sent', [LeadController::class, 'markFollowupPlanSent']);
        Route::delete('/followup-plans/{id}', [LeadController::class, 'deleteFollowupPlan']);

        Route::get('/learning/questions', [LearningController::class, 'index']);
        Route::get('/learning/questions/{id}', [LearningController::class, 'show']);
        Route::post('/learning/questions/{id}/resolve', [LearningController::class, 'resolve']);

        Route::get('/analytics/lead-source-behavior', [AnalyticsController::class, 'leadSourceBehavior']);
        Route::get('/analytics/tag-distribution', [AnalyticsController::class, 'tagDistribution']);
        Route::get('/analytics/conversion-by-tag', [AnalyticsController::class, 'conversionByTag']);
        Route::get('/analytics/followup-performance', [AnalyticsController::class, 'followupPerformance']);
        Route::get('/analytics/reply-source-breakdown', [AnalyticsController::class, 'replySourceBreakdown']);
        Route::get('/analytics/campaign-performance', [AnalyticsController::class, 'campaignPerformance']);
        Route::get('/analytics/unresolved-learning-trends', [AnalyticsController::class, 'unresolvedLearningTrends']);

        Route::get('/campaigns/types', [CampaignController::class, 'types']);
        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
        Route::get('/campaigns/{id}/recipients', [CampaignController::class, 'recipients']);
        Route::post('/promotions/tag-send', [CampaignController::class, 'sendTagPromotion']);

        Route::get('/outbound/types', [OutboundController::class, 'types']);
        Route::get('/outbound', [OutboundController::class, 'index']);
        Route::get('/cohorts/expired-clients', [CohortController::class, 'expiredClients']);
        Route::get('/cohorts/unsubscribed-registrations', [CohortController::class, 'unsubscribedRegistrations']);
        Route::post('/cohorts/{cohort}/followups', [CohortController::class, 'createFollowups']);
        Route::post('/cohorts/{cohort}/outbound', [CohortController::class, 'queueOutbound']);
        Route::post('/cohorts/{cohort}/sms', [CohortController::class, 'sendSms']);
        Route::get('/renewal-batches', [CohortController::class, 'listBatches']);
        Route::post('/renewal-batches', [CohortController::class, 'storeBatch']);
        Route::get('/renewal-batches/{id}', [CohortController::class, 'showBatch']);
        Route::post('/renewal-batches/{id}/run', [CohortController::class, 'runBatch']);
        Route::post('/renewal-batches/{id}/clone', [CohortController::class, 'cloneBatch']);
        Route::get('/renewal-batches/{id}/export', [CohortController::class, 'exportBatchRecipients']);
        Route::post('/renewal-batches/{id}/archive', [CohortController::class, 'archiveBatch']);
        Route::delete('/renewal-batches/{id}', [CohortController::class, 'destroyBatch']);

        Route::get('/training/{botType}', [TrainingController::class, 'index']);
        Route::post('/training/{botType}', [TrainingController::class, 'store']);
        Route::delete('/training/{botType}/{id}', [TrainingController::class, 'destroy']);
        Route::get('/knowledge/items', [KnowledgeController::class, 'index']);
        Route::post('/knowledge/items', [KnowledgeController::class, 'store']);
        Route::get('/knowledge/items/{id}', [KnowledgeController::class, 'show']);
        Route::patch('/knowledge/items/{id}', [KnowledgeController::class, 'update']);
        Route::delete('/knowledge/items/{id}', [KnowledgeController::class, 'destroy']);
        Route::get('/live-client-showcases', [LiveClientShowcaseController::class, 'index']);
        Route::post('/live-client-showcases', [LiveClientShowcaseController::class, 'store']);
        Route::get('/live-client-showcases/{id}', [LiveClientShowcaseController::class, 'show']);
        Route::patch('/live-client-showcases/{id}', [LiveClientShowcaseController::class, 'update']);
        Route::delete('/live-client-showcases/{id}', [LiveClientShowcaseController::class, 'destroy']);

        Route::get('/handoffs/{sessionId}', [HandoffController::class, 'show']);
        Route::post('/handoffs/{sessionId}/resolve', [HandoffController::class, 'resolve']);
        Route::post('/handoffs/{sessionId}/assign-bot', [HandoffController::class, 'assignBot']);
        Route::post('/handoffs/{sessionId}/messages', [HandoffController::class, 'sendMessage']);
        Route::get('/review/handoffs', [ReviewController::class, 'handoffs']);
        Route::get('/review/abusive', [ReviewController::class, 'abusive']);
        Route::get('/review/manual', [ReviewController::class, 'manual']);
        Route::get('/review/unclear', [ReviewController::class, 'unclear']);
        Route::get('/review/dropped', [ReviewController::class, 'dropped']);
        Route::post('/review/{sessionId}/assign-human', [ReviewController::class, 'assignHuman']);
        Route::post('/review/{sessionId}/disable-bot', [ReviewController::class, 'disableBot']);
        Route::post('/review/{sessionId}/enable-bot', [ReviewController::class, 'enableBot']);
        Route::post('/review/{sessionId}/resolve', [ReviewController::class, 'resolve']);
        Route::post('/review/{sessionId}/note', [ReviewController::class, 'note']);
    });
});

//facebook pixel and google analytics status
Route::prefix('v2')->group(function () {
    Route::get('/marketing-modules-status/{store}', [MarketingController::class, 'index']);
    Route::post('/meta-conversions/{store}', [MarketingController::class, 'trackMetaConversion']);
});

Route::post('/v2/address/easy-order/save', [UserController::class, 'saveaddress']);

Route::get('/v2/modules/{store}', [UserController::class, 'modules']);
Route::get('/v2/get-module-info/{store}', [UserController::class, 'getModuleInfo']);
Route::get('/v2/get-module/{store}/{id}', [UserController::class, 'getModuleById']);

Route::get('/v2/brand/{store}', [UserController::class, 'getBrand']);
Route::post('/v2/fileUpload', [MailController::class, 'fileUpload']);

Route::get('/v2/get/district', [DistrictController::class, 'getAllDistrict']);
Route::get('/v2/get/district/{id}', [DistrictController::class, 'getDistrictById']);
Route::get('/v2/get/country', [CountryController::class, 'getAllCountry']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/v2/getuser', [LoginController::class, 'getuser']);
    Route::post('/v2/password-change', [UserController::class, 'changepass']);
    Route::post('/v2/user/updateprofile', [UserController::class, 'updateuser']);
    Route::post('/v2/order/cancel', [OrderController::class, 'cancelorder']);
    Route::get('/v2/getorder/details/{id}', [OrderController::class, 'orderdetails']);
    Route::get('/v2/getorder/{store}', [OrderController::class, 'getorder']);
    Route::post('/v2/verifyotp', [LoginController::class, 'verifyotp']);
    Route::post('/v2/logout', [LoginController::class, 'logout']);
    Route::get('/v2/admin/logout', [LoginController::class, 'admin_logout']);
    Route::post('/v2/review', [OrderController::class, 'review']);
    Route::get('/v2/address', [UserController::class, 'address']);
    Route::post('/v2/address/save', [UserController::class, 'saveaddress']);

    Route::post('/v2/address/edit', [UserController::class, 'updateaddress']);
    Route::post('/v2/address/delete', [UserController::class, 'deleteaddress']);

    Route::get('/v2/get/order-status', [OrderController::class, 'getOrderStatus']);

});

Route::post('/v2/placeorder', [OrderController::class, 'placeorder']);
Route::get('/v2/get-store-notification/{user}/{store?}', [AdminNotificationController::class, 'getStoreNotification']);


Route::post('v2/auth/socia-id', [QuickLoginController::class, 'sociaId']);
Route::group(['prefix' => 'v2'], function () {
    // your routes here
    Route::post('auth/socia-id', [QuickLoginController::class, 'sociaId']);
    Route::post('auth/google/login', [QuickLoginController::class, 'googleLogin']);

    Route::get('/subdomain/name/validate', [SubdomainController::class, 'index']);
    Route::get('/getsearch', [SubdomainController::class, 'getsearch']);
    Route::get('/subdomain/header/name', [SubdomainController::class, 'sendheader']);
    Route::post('/login', [LoginController::class, 'index']);
    Route::post('/paymentlogin', [LoginController::class, 'paymentlogin']);
    Route::post('/register', [LoginController::class, 'register']);
    Route::post('/user/register', [LoginController::class, 'register']);

    //My code here start
    Route::get('/store/{name}/info', [SubdomainController::class, 'getStore']);
    Route::get('/storefront/bootstrap', [SubdomainController::class, 'storefrontBootstrap']);
    Route::get('/get-domain/{name}/{section}', [SubdomainController::class, 'getDomainSection']);

    Route::get('/get/attribute/{store}/{name}', [SubdomainController::class, 'getAttribute']);
    //My code here end

    Route::post('/brand', [SubdomainController::class, 'getAllBrandProducts']);
    Route::get('/getcatproducts/{id}', [SubdomainController::class, 'getcatproduct']);
    Route::get('/getsubcatproduct/{id}', [SubdomainController::class, 'getsubcatproduct']);
    Route::get('/gettagproduct/{store}/{tag}', [SubdomainController::class, 'getTagProduct']);

    Route::get('/get/brand-products/{id}', [SubdomainController::class, 'getBrandProduct']);

    Route::get('/campaign/{store}', [SubdomainController::class, 'campaign']);

    Route::get('/product/search/{store}/{search?}', [SubdomainController::class, 'productSearch']);

    Route::get('/verifycoupon/{store}/{code}', [SubdomainController::class, 'verifycoupon']);
    Route::get('/verifycoupon-auto-apply/{store}/{amount}', [SubdomainController::class, 'couponAutoApply']);
    Route::get('/check/coupon-is-available/{store}', [SubdomainController::class, 'availableCoupon']);

    Route::post('/admin/verify-coupon', [SubdomainController::class, 'adminVerifyCoupon']);

    Route::get('/product-details/{store}/{id}', [SubdomainController::class, 'getdetails']);

    Route::post('/getcodeproduct', [PosController::class, 'getsearchproductbarcode']);

    Route::post('/change-password', [LoginController::class, 'changepass']);
    Route::post('/forget-pass', [LoginController::class, 'forget']);
    Route::post('/forget-verify', [LoginController::class, 'forgetverify']);
    Route::post('/user/details', [UserController::class, 'userdetails']);
    Route::get('/plan-details', [SubdomainController::class, 'plandetails']);
    Route::get('/homepage/layout', [SubdomainController::class, 'homepagelayout']);
    Route::get('/page/{store}/{slug}', [SubdomainController::class, 'pages']);
    Route::get('/related-product/{id}', [SubdomainController::class, 'relatedproduct']);
    Route::get('/get/review/{id}', [SubdomainController::class, 'getreview']);
    Route::get('/get/offer/product/{store}/{id}', [SubdomainController::class, 'checkoffer']);
    Route::get('/shoppage/products', [SubdomainController::class, 'getshoppageproduct']);
    Route::get('/apps/url', [SubdomainController::class, 'appsurl']);
    Route::post('/page/payment', [PaymentPageController::class, 'index']);
    Route::post('/placeplan', [PaymentPageController::class, 'placeplan']);
    Route::post('/addons-buy', [PaymentPageController::class, 'addonsBuy']);
    Route::get('/addons', [PaymentPageController::class, 'addons']);
    Route::post('/payment-history', [PaymentPageController::class, 'paymentHistory']);
    Route::post('/checkactive', [PaymentPageController::class, 'activepage']);
    Route::post('/deactivestore', [PaymentPageController::class, 'deactivestore']);
    Route::get('/popup/image', [SubdomainController::class, 'popupimage']);
    Route::get('/getnotification', [SubdomainController::class, 'getnotification']);

    Route::post('/saveslider', [ImageController::class, 'saveslider']);
    Route::post('/savebanner', [ImageController::class, 'savebanner']);
    Route::post('/savetestimonials', [ImageController::class, 'savetestimonials']);
    Route::post('/savehs', [ImageController::class, 'savehs']);
    Route::post('/saveuserimage', [ImageController::class, 'saveuserimage']);
    Route::post('/savetoken', [ImageController::class, 'savetoken']);
    Route::post('/savemapp', [ImageController::class, 'savemapp']);
    Route::post('/savebrand', [ImageController::class, 'savebrand']);
    Route::post('/savecat', [ImageController::class, 'savecat']);
    Route::post('/saveproduct', [ImageController::class, 'saveproduct']);
    Route::get('/templates', [SubdomainController::class, 'templates']);
    Route::get('/checkout-page/form-field/{store}', [DesignController::class, 'getDesignCheckoutForm']);
    Route::get('/get/courier-list/{store}', [CourierController::class, 'getCourierList']);

    Route::post('/initialactiveplan', [PaymentPageController::class, 'initialactiveplan']);

    Route::post('/userinfo', [UserController::class, 'userinfo']);
    Route::post('/user-registration-email', [UserController::class, 'userRegistrationEmail']);

    Route::post('/users/checkotp', [UserController::class, 'checkotps']);
    Route::post('/user/resendotp', [UserController::class, 'rsendotps']);

    Route::post('/user/registration', [UserController::class, 'registers']);
    Route::post('/user/registration/check', [UserController::class, 'registerscheck']);
    Route::post('/check/user/phone', [UserController::class, 'checkUserPhone'])->name("admin.check.user.phone");

    Route::post('/getcatpos', [PosController::class, 'getcat']);
    Route::post('/getproducts', [PosController::class, 'getproducts']);
    Route::post('/addtocart', [PosController::class, 'addtocart']);
    Route::post('/getcarts', [PosController::class, 'getcarts']);
    Route::post('/incrementcart', [PosController::class, 'incrementcart']);
    Route::post('/decrementcart', [PosController::class, 'decrementcart']);
    Route::post('/removecart', [PosController::class, 'removecart']);
    Route::post('/addveritocart', [PosController::class, 'addveritocart']);
    Route::post('/getcatproduct', [PosController::class, 'getcatproduct']);
    Route::post('/getcustomer', [PosController::class, 'getcustomer']);
    Route::post('/posorder', [PosController::class, 'posorder']);
    Route::post('/posorderhold', [PosController::class, 'posorderhold']);
    Route::post('/getholdorders', [PosController::class, 'getholdorders']);
    Route::post('/holdorderproduct', [PosController::class, 'holdorderproduct']);
    Route::post('/deleteholdorder', [PosController::class, 'deleteholdorder']);
    Route::get('/getorderid', [PosController::class, 'getorderid']);
    Route::post('/editholdorders', [PosController::class, 'editholdorders']);
    Route::post('/getsearchproduct', [PosController::class, 'getsearchproduct']);
    Route::post('/getsearchproductbarcode', [PosController::class, 'getsearchproductbarcode']);

    Route::get('/digitaltimmer', [SubdomainController::class, 'digitaltimmer']);

    Route::post('/app-status', [SubdomainController::class, 'appStatus']);

    //Bolg
    Route::group(['prefix' => '/blog'], function () {
        Route::get('/get/{store?}', [AdminBlogController::class, 'index']);
        Route::get('/details/{slug}', [AdminBlogController::class, 'show']);
        Route::get('/get-types/{store?}', [AdminBlogController::class, 'blogTypes']);
        Route::get('/types/{id}', [AdminBlogController::class, 'typeBlogs']);
        Route::get('/popular/{store?}', [AdminBlogController::class, 'popularBlog']);
        Route::get('/recent/{store?}', [AdminBlogController::class, 'recentBlog']);
        Route::get('/site-map/{store?}', [AdminBlogController::class, 'siteMap']);
    });
    //End Blog

    // Insert visitor data
    Route::post("store/visitor-data", [AdminVisitorController::class, 'storeVisitorData']);
    Route::patch("update/visitor-data", [AdminVisitorController::class, 'updateVisitorData']);

    // Announcement
    Route::get('/get-announcement/{store}', [AnnouncementController::class, 'index']);


    //PSE Marketplace
    Route::group(['prefix' => '/pse'], function () {
        Route::group(['prefix' => '/products'], function () {
            Route::get('/', [MarketplaceController::class, 'index']);
            Route::get('/categories', [MarketplaceController::class, 'getAllCategories']);
            Route::get('/search', [MarketplaceController::class, 'searchProductByName']);
            Route::get('/product-by-category', [MarketplaceController::class, 'searchProductIdAndName']);
            Route::post('/visitor', [MarketplaceController::class, 'visitorCounter']);
            Route::get('/category', [MarketplaceController::class, 'categoryProduct']);
            Route::get('/top-pik-products', [MarketplaceController::class, 'topPicProduct']);
        });

        Route::group(['prefix' => '/ads'], function () {
            Route::get('/', [PseAdsController::class, 'index']);
        });
    });
    //PSE Marketplace end

    // Ebitans Analytics hk
    Route::get('/ebi-analytics', [EbtAnalyticsController::class, 'index']);
    Route::post('/ebi-analytics/store', [EbtAnalyticsController::class, 'store']);
    // End Ebitans Analytics hk


    // NewsLetter hk
    Route::get('/news-latter', [NewsLetterController::class, 'index']);
    Route::post('/news-latter/store', [NewsLetterController::class, 'store']);

    Route::get('/admin-noti/{id}', [NewsLetterController::class, 'getNotiFica']);
    // End NewsLetter hk

    // User
    Route::get('/bkash/checkout-url/orderPay', [BkashController::class, 'orderPay'])->name('bkash.payment');
    Route::get('/admin/bkash/checkout-url/orderPay', [AdminBkashController::class, 'orderPay'])->name('admin.bkash.payment');

    Route::get('/booking-from/{store}/{id?}', [BookingController::class, 'index']);

    //api theme controller
    Route::controller(ThemeController::class)->group(function () {
        // header setting
        Route::get('/header-settings/{name}/info', 'headerSettings');

        // layout
        Route::prefix('/layout')->group(function () {
            Route::post('/product', 'layoutProducts');
            Route::get('/products/{name}', 'getProductForLayout');
        });
    });

    //product_affiliate
    Route::controller(ProductAffiliateController::class)->prefix('/customer-affiliate')->group(function () {
        Route::post('/register', 'register');
        Route::post('/create/withdraw-requests', 'createWithdrawRequest');
        Route::get('/withdraw-requests/{status?}', 'getWithdrawRequest')->middleware(['auth:sanctum']);
        Route::get('/order-list', 'getAffiliateOrderDetails')->middleware(['auth:sanctum']);
    });

    /******** abandoned cart start *********/

    Route::get('/store/cart/items', [CartController::class, 'getCartItems'])->name('getCartItems');
    Route::post('/store/cart/add', [CartController::class, 'addToCart'])->name('addToCart');
    Route::delete('/store/cart/remove', [CartController::class, 'deleteCartItem'])->name('deleteCart');
    Route::delete('/store/cart/clear', [CartController::class, 'clearCart'])->name('clearCart');
    Route::post('/store/cart/add-contact', [CartController::class, 'addContactToCart'])->name('addContactToCart');

    /******** abandoned cart end *********/


    Route::get('/pse/product/search', [\App\Http\Controllers\Api\v2\ProductSearchController::class, 'search']);

});
