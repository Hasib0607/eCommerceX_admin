<?php

namespace App\Http\Controllers;

use App\Http\Traits\ActivityLogTraits;
use App\Models\Banner;
use App\Models\CheckoutForm;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Designlist;
use App\Models\DesignPosition;
use App\Models\Headersetting;
use App\Models\Menu;
use App\Models\Slider;
use App\Models\Store;
use App\Models\StoreDesign;
use App\Models\Testimonial;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Image;
use Session;

class DesignController extends Controller
{
    use ActivityLogTraits;

    public function __construct()
    {
        $this->middleware('auth')->except('getDesignCheckoutForm');
    }

    public function changesliderstatus(Request $request)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $id = $request->id;
        $value = $request->value;
        $slider = Slider::where('store_id', $store_id)->where('id', $id)->first();
        if (empty($slider)) {
            return back();
        }
        if (isset($slider) && $slider->status == 'active') {
            $slider->status = 'inactive';
        } else {
            $slider->status = "active";
        }
        $slider->save();
        $data = $slider;
        $activity = " Change Slider Status for Id " . $slider->id;
        $this->saveactivity($activity);
        return response()->json($data);
    }

    public function updatepositionslider(Request $request)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $value = $request->value;
        $id = $request->id;
        $test = Slider::where('store_id', $store_id)->where('id', $id)->first();
        if (empty($test)) {
            return back();
        }
        $test->position = $value;
        $test->save();
        // $data=$test;
        $data = $test;
        $activity = " Update Slider Position for " . $test->id;
        $this->saveactivity($activity);
        return response()->json($data);
    }

    public function sliderexport(Request $request)
    {
        $date = Carbon::now();
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $fileName = 'slider(' . $date . ').csv';
        $coupon = Slider::where('store_id', $store_id)->get();

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = array('Image', 'Title', 'Sub Title', 'Link', 'Status', 'Created_at');

        $callback = function () use ($coupon, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($coupon as $cat) {
                $row['Image'] = $cat->image;
                $row['Title'] = $cat->title;
                $row['Sub Title'] = $cat->subtitle;
                $row['Link'] = $cat->link;
                $row['Status'] = $cat->status;
                $row['Create Date'] = $cat->created_at;

                fputcsv($file, array(
                    $row['Image'],
                    $row['Title'],
                    $row['Sub Title'],
                    $row['Link'],
                    $row['Status'],
                    $row['Create Date']
                ));
            }

            fclose($file);
        };
        $activity = " Export Slider";
        $this->saveactivity($activity);
        return response()->stream($callback, 200, $headers);
    }

    public function testimonialexport(Request $request)
    {
        $date = Carbon::now();

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $fileName = 'testimonial(' . $date . ').csv';
        $coupon = Testimonial::where('store_id', $store_id)->get();

        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $columns = array('Name', 'Occupation', 'Feedback', 'Created_at');

        $callback = function () use ($coupon, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($coupon as $cat) {
                $row['Name'] = $cat->name;
                $row['Occupation'] = $cat->occupation;
                $row['Feedback'] = $cat->feedback;
                $row['Create Date'] = $cat->created_at;

                fputcsv($file, array($row['Name'], $row['Occupation'], $row['Feedback'], $row['Create Date']));
            }

            fclose($file);
        };
        $activity = " Export Testimonial";
        $this->saveactivity($activity);
        return response()->stream($callback, 200, $headers);
    }

    public function saveslider(Request $request)
    {
        $rules = array(
            'image' => 'required',
            'position' => 'required',
            'status' => 'required'
        );

        $message = array(
            'image.required' => 'image is required.',
            'position.required' => 'Position is required.',
            'status.required' => 'status is required.',
        );
        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return redirect()->back()->withInput()
                ->withErrors($validator);
        } else {
            $userData = getUserData();
            $store_id = $userData['store_id'];

            $slider = new Slider;
            $slider->title = $request->title;
            $slider->subtitle = $request->subtitle;

            if ($request->input('image')) {
//                $image = $request->file('image');
//                $validation = imageValidation($image, $store_id);
//                if ($validation) {
//                    return back()->with('warning', $validation);
//                }
//
//                $imageUploadPath = 'assets/images/slider/';
//                $imageName = uploadFile($image, $imageUploadPath);
                $slider->image = getLibraryImagePath($request->image);
            }

            if ($request->file('subimage')) {
                $image = $request->file('subimage');
                $validation = imageValidation($image, $store_id);
                if ($validation) {
                    return back()->with('warning', $validation);
                }

                $imageUploadPath = 'assets/images/slider/';
                $imageName = uploadFile($image, $imageUploadPath);
                $slider->subimage = $imageName;
            }

            $slider->link = $request->link;
            $slider->position = $request->position;
            if ($request->status == 'on') {
                $slider->status = 'active';
            } else {
                $slider->status = 'inactive';
            }
            $user_id = Auth::user()->id;
            $user_type = Auth::user()->type;
            if ($user_type == "admin" || $user_type == "dropshipper") {
                $customer = Customer::where('uid', $user_id)->first();
            }
            $store_id = $customer->active_store;

            $slider->uid = Auth::user()->id;
            $slider->customer_id = $customer->id;
            $slider->store_id = $store_id;
            $slider->color = $request->color;
            $slider->creator = $user_id;
            $slider->editor = $user_id;
            if (isset($request->subtitle_color)) {
                $slider->subtitle_color = $request->subtitle_color;
            }
            if (isset($request->button)) {
                $slider->button = $request->button;
                $slider->button_color = $request->button_color;
            }
            $slider->save();

            if (isset($request->design_title) || isset($request->button)) {
                $storeDesign = StoreDesign::firstOrNew(['store_id' => $store_id, 'type' => 'slider']);
                $storeDesign->title = $request->design_title ?? null;
                $storeDesign->title_color = $request->design_title_color ?? null;
                $storeDesign->button = $request->button ?? null;
                $storeDesign->button_color = $request->button_color ?? null;
                $storeDesign->save();
            }


            $activity = " Save Slider " . $slider->id;
            $this->saveactivity($activity);
            Session::flash('message', 'Slider Save Successfully !');
            return redirect()->route('admin.design.homepage.slider');
        }
    }

    public function updateslider(Request $request, $id)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $rules = array(
            'position' => 'required',
            'status' => 'required'
        );

        $message = array(
            'position.required' => 'Position is required.',
            'status.required' => 'status is required.',
        );

        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        } else {
            $slider = Slider::where('store_id', $store_id)->where('id', $id)->first();
            if (empty($slider)) {
                return back();
            }
            $slider->title = $request->title;
            $slider->subtitle = $request->subtitle;

            if ($request->input('image')) {
//                $image = $request->file('image');
//                $validation = imageValidation($image, $store_id);
//                if ($validation) {
//                    return back()->with('warning', $validation);
//                }
//
//                $imageUploadPath = 'assets/images/slider/';
//                $imageName = updateFile($image, $imageUploadPath, $slider->image);
                $slider->image = getLibraryImagePath($request->image);
            }

            if ($request->file('subimage')) {
                $image = $request->file('subimage');
                $validation = imageValidation($image, $store_id);
                if ($validation) {
                    return back()->with('warning', $validation);
                }

                $imageUploadPath = 'assets/images/slider/';
                $imageName = updateFile($image, $imageUploadPath, $slider->subimage);
                $slider->subimage = $imageName;
            }

            $slider->link = $request->link;
            $slider->position = $request->position;
            if ($request->status == 'on') {
                $slider->status = 'active';
            } else {
                $slider->status = 'inactive';
            }
            $user_id = Auth::user()->id;
            $user_type = Auth::user()->type;
            if ($user_type == "admin" || $user_type == "dropshipper") {
                $customer = Customer::where('uid', $user_id)->first();
            }
            $store_id = $customer->active_store;
            $store = Store::where('id', $store_id)->first();
            $slider->editor = $user_id;
            $slider->color = $request->color;
            if (isset($request->subtitle_color)) {
                $slider->subtitle_color = $request->subtitle_color;
            }
            if (isset($request->button)) {
                $slider->button = $request->button;
                $slider->button_color = $request->button_color;
            }
            $slider->save();

            if (isset($request->design_title) || isset($request->button)) {
                $storeDesign = StoreDesign::firstOrNew(['store_id' => $store_id, 'type' => 'slider']);
                $storeDesign->title = $request->design_title ?? null;
                $storeDesign->title_color = $request->design_title_color ?? null;
                $storeDesign->button = $request->button ?? null;
                $storeDesign->button_color = $request->button_color ?? null;
                $storeDesign->save();
            }

            $activity = " Update Slider " . $slider->id;
            $this->saveactivity($activity);
            Session::flash('message', 'Slider Update Successfully !');
            return redirect()->route('admin.design.homepage.slider');
        }
    }

    public function deleteslider($id)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        if (canAccess('slider')) {
            $slider = Slider::where('store_id', $store_id)->where('id', $id)->first();
            if (empty($slider)) {
                return back();
            }
            $activity = " Delete Slider " . $slider->id;
            $this->saveactivity($activity);
            $slider->delete();
            Session::flash('success_message', 'Slider Deleted Successfully !');
            return redirect()->route('admin.design.homepage.slider');
        }
    }

    public function deleteSliderImage($id)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $slider = Slider::where('store_id', $store_id)->where('id', $id)->first();
        if (!isset($slider)) {
            return sendError("Slider not found");
        }
        $slider->image = null;
        $slider->update();

        return sendResponse("Slider Image Deleteed Successfully");
    }

    public function changebannerstatus(Request $request)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $id = $request->id;
        $value = $request->value;
        $banner = Banner::where('store_id', $store_id)->where('id', $id)->first();
        if (empty($banner)) {
            return back();
        }
        if (isset($banner) && $banner->status == 'active') {
            $banner->status = 'inactive';
        } else {
            $banner->status = "active";
        }
        $banner->save();
        $data = $banner;
        $activity = " Change Banner Status " . $banner->id;
        $this->saveactivity($activity);
        return response()->json($data);
    }

    public function savebanner(Request $request)
    {
        $rules = array(
            'image' => 'required',
            'status' => 'required'
        );

        $message = array(
            'image.required' => 'image is required.',
            'status.required' => 'status is required.',
        );

        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        } else {
            /*extract user_id, user_type, store_id,customer, customer_id*/
            extract(getUserData());
            $banner = new Banner;

            if ($request->input('image')) {
//                $image = $request->file('image');
//                $validation = imageValidation($image, $store_id);
//                if ($validation) {
//                    return back()->with('warning', $validation);
//                }
//
//                $imageUploadPath = 'assets/images/banner/';
//                $imageName = uploadFile($image, $imageUploadPath);
                $banner->image = getLibraryImagePath($request->image);
            }

            $banner->link = $request->link;
            if ($request->status == 'on') {
                $banner->status = 'active';
            } else {
                $banner->status = 'inactive';
            }

            $banner->type = $request->type ?? 0;
            $banner->uid = $user_id;
            $banner->customer_id = $customer_id;
            $banner->store_id = $store_id;
            $banner->creator = $user_id;
            $banner->editor = $user_id;
            $banner->save();
            $activity = " Save banner " . $banner->id;
            $this->saveactivity($activity);
            Session::flash('success', 'Banner Save Successfully !');
            return redirect()->back();
        }
    }

    public function updatebanner(Request $request, $id)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $rules = array(
            'status' => 'required'
        );

        $message = array(
            'status.required' => 'status is required.',
        );
        $validator = Validator::make($request->all(), $rules, $message);
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator);
        } else {
            $banner = Banner::where('store_id', $store_id)->where('id', $id)->first();
            if (empty($banner)) {
                return back();
            }

            if ($request->input('image')) {
//                $image = $request->file('image');
//                $validation = imageValidation($image, $store_id);
//                if ($validation) {
//                    return back()->with('warning', $validation);
//                }
//
//                $imageUploadPath = 'assets/images/banner/';
//                $imageName = updateFile($image, $imageUploadPath, $banner->image);
                $banner->image = getLibraryImagePath($request->image);
            }

            $banner->link = $request->link;
            if ($request->status == 'on') {
                $banner->status = 'active';
            } else {
                $banner->status = 'inactive';
            }

            $banner->type = $request->type ?? 0;

            $banner->editor = $user_id;
            $banner->save();
            $this->save_store_design($request, $store_id, 'banner');
            $activity = " Update Banner " . $banner->id;
            $this->saveactivity($activity);
            Session::flash('success', 'Banner Update Successfully !');
            return redirect()->back();
        }
    }

    private function save_store_design($request, $store_id, $type)
    {
        if (isset($request->title) || isset($request->button)) {
            $storeDesign = StoreDesign::firstOrNew(['store_id' => $store_id, 'type' => $type]);
            $storeDesign->title = $request->title ?? null;
            $storeDesign->title_color = $request->title_color ?? null;
            $storeDesign->button = $request->button ?? null;
            $storeDesign->button_color = $request->button_color ?? null;
            $storeDesign->save();
        }
    }

    public function deletebanner($id)
    {

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        if (canAccess('banner')) {
            $banner = Banner::where('store_id', $store_id)->where('id', $id)->first();
            if (empty($banner)) {
                return back();
            }
            $activity = " Delete Banner " . $banner->id;
            $this->saveactivity($activity);
            $banner->delete();
            Session::flash('success', 'Banner Deleted Successfully !');
            return redirect()->back();
        }
    }

    public function deleteBannerImage($id)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'];

        $banner = Banner::where('store_id', $store_id)->where('id', $id)->first();
        if (!isset($banner)) {
            return sendError("Banner not found");
        }
        $banner->image = null;
        $banner->update();

        return sendResponse("Banner Image Deleteed Successfully");
    }

    public function filterdesign(Request $request)
    {
        if (canAccess('header')) {
            $urls = "design";
            $menu = Menu::all();

            /*extract user_id, user_type, store_id,customer, customer_id*/
            extract(getUserData());

            if ($request->categoryfilter == 'all') {
                $design = Designlist::where('type', 'header')->where('status', 'active')->get();
            } else {
                $design = Designlist::where('type',
                    'header')->whereRaw('FIND_IN_SET("' . $request->categoryfilter . '",category)')->where('status',
                    'active')->get();
            }
            $stts = $request->categoryfilter;
            $activity = " Filter Header Design Page By " . $stts;
            $this->saveactivity($activity);
            return view('admin.design.header.design')->with('urls', $urls)->with('store_id',
                $store_id)->with('design', $design)->with('stts', $stts);
        }
    }


    public function header_design_save(Request $request)
    {
//        return $request->all();
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount(' Header Menu', "title.png", "/design/header");

        $design = Design::where('store_id', $store_id)->first();

        if (!isset($design)) {
            $design = new Design;
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
        }
        /*$design->header = $request->header;*/
        $design->header_color = $request->headercolor;
        $design->text_color = $request->textcolor;
        $design->save();

        $menus = $request->menus;
        if (isset($menus) && count($menus) > 0) {
            foreach ($menus as $menu) {
                // Check if the 'active' key exists in the menu item
                if (isset($menu['active'])) {
                    $status = 1; // Set status to 1 if 'active' is present
                } else {
                    $status = 0; // Default status
                }

                // Query the menu item in the database
                $_menu = Menu::where('store_id', $store_id)
                    ->where('id', $menu['id'])
                    ->first();

                $slug = $menu['name'] == "Home" || $menu['name'] == "home" ? '' : generateSlug($menu['name']);

                // If menu exists, update its status
                if ($_menu) {
                    $_menu->update([
                        'status' => $status, // Update status
                        'name' => $menu['name'], // Update other fields as needed
                        'url' => $slug, // Update other fields as needed
                        'sort' => $menu['sort'],
                        'custom_link' => $menu['custom_link'],
                    ]);

                } else {
                    // If menu doesn't exist, create a new entry
                    if (isset($menu['name']) && !empty($menu['name'])) {
                        Menu::create([
                            'store_id' => $store_id,
                            'uid' => $user_id,
                            'url' => $slug,
                            'name' => $menu['name'],
                            'status' => $status,
                            'sort' => $menu['sort'],
                            'custom_link' => $menu['custom_link'],
                            'creator' => $user_id,
                            'editor' => $user_id,
                            'customer_id' => $customer->id,
                        ]);
                    }
                }
            }
        }

        $this->saveactivity(" Create/Update Menu");
        $this->saveactivity(" Create/Update Design");
        \Illuminate\Support\Facades\Session::flash('success', 'Menu and Design Update Successfully');
        return redirect()->back();

    }

    public function saveheadermenu(Request $request)
    {
        dd($request->all());

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount(' Header Menu', "title.png", "/design/header");

        /*$menu = Menu::where('store_id', $store_id)->get();
        if (isset($menu)) {
            if (count($menu) > 0) {
                foreach ($menu as $m) {
                    $ms = Menu::find($m->id);
                    $ms->delete();
                }
            }
        }

        if (isset($request->home)) {
            if (count($request->home) > 0) {
                foreach ($request->home as $key => $home) {
                    $menu = new Menu();

                    foreach ($request->menuselect as $keyss => $name) {
                        if ($name == $home) {
                            $menu->name = $request->homename[$keyss];
                            $menu->url = $request->url[$keyss];
                            $menu->sort = $request->homesort[$keyss];
                        }
                    }
                    $menu->uid = $user_id;
                    $menu->customer_id = $customer->id;
                    $menu->store_id = $customer->active_store;
                    $menu->creator = $user_id;
                    $menu->editor = $user_id;
                    $menu->save();
                }
            }
        }*/

        /*$menu = Menu::where('store_id', $store_id)->get();
        if (isset($menu)) {
            if (count($menu) > 0) {
                foreach ($menu as $m) {
                    $ms = Menu::find($m->id);
                    $ms->delete();
                }
            }
        }*/

//                foreach ($request->home as $key => $home) {
//                    $menu = Menu::where('store_id', $store_id)->where('name', $home)->first();
//
//                    if(!isset($menu)){
//                        $menu = new Menu();
//                    }
//
//                    foreach ($request->menuselect as $keyss => $name) {
//                        if ($name == $home) {
//                            $menu->name = $request->homename[$keyss];
//                            $menu->url = $request->url[$keyss];
//                            $menu->sort = $request->homesort[$keyss];
//                        }
//                    }
//                    $menu->uid = $user_id;
//                    $menu->customer_id = $customer->id;
//                    $menu->store_id = $customer->active_store;
//                    $menu->creator = $user_id;
//                    $menu->editor = $user_id;
//                    $menu->save();
//                }

        /*foreach ($request->home as $key => $home) {
            $menu = new Menu();

            foreach ($request->menuselect as $keyss => $name) {
                if ($name == $home) {
                    $menu->name = $request->homename[$keyss];
                    $menu->url = $request->url[$keyss];
                    $menu->sort = $request->homesort[$keyss];
                }
            }
            $menu->uid = $user_id;
            $menu->customer_id = $customer->id;
            $menu->store_id = $customer->active_store;
            $menu->creator = $user_id;
            $menu->editor = $user_id;
            $menu->save();
        }*/
        $activity = " Create New Menu";
        $this->saveactivity($activity);
        Session::flash('success_message', 'Menu Created Successfully');
        return back();
    }

    public function headersettings()
    {
        if (canAccess('header')) {
            $urls = "design";

            /*extract user_id, user_type, store_id,customer, customer_id*/
            extract(getUserData());
            $store = Store::where('id', $store_id)->first();
            $setting = Headersetting::convertCurrency($store_id)->first();
            //  dd($setting);
            $activity = " Access Header Settings Page";
            $this->saveactivity($activity);
            return view('admin.design.header.settings')->with('urls', $urls)->with('store', $store)->with('setting',
                $setting);
        }

        return redirect()->back();
    }

    public function saveheadersettings(Request $request)
    {
        $user_id = Auth::user()->id;
        $user_type = Auth::user()->type;
        if ($user_type == "admin" || $user_type == "dropshipper") {
            $customer = Customer::where('uid', $user_id)->first();
        }
        $store = Store::where('id', $customer->active_store)->first();
        $setting = Headersetting::where('store_id', $customer->active_store)->first();
        if (isset($setting)) {
            $hs = Headersetting::where('store_id', $store->id)->where('id', $setting->id)->first();
            if (empty($hs)) {
                return back();
            }
            if ($request->logo) {
                $imgName = Carbon::now()->timestamp . '.' . $request->logo->extension();
                $request->logo->storeAs('setting', $imgName);
                $hs->logo = $imgName;
            }
            $hs->phone = $request->phone;
            $hs->address = $request->address;
            $hs->editor = $user_id;
            $hs->save();
            $activity = " Save Header Settings";
            $this->saveactivity($activity);
            Session::flash('success_message', 'Header Setting Updated Successfully');
            return back();
        } else {
            $hs = new Headersetting();
            if ($request->logo) {
                $imgName = Carbon::now()->timestamp . '.' . $request->logo->extension();
                $request->logo->storeAs('setting', $imgName);
                $hs->logo = $imgName;
            }
            $hs->currency_id = $store->currency;
            $hs->website_name = $store->name;
            $hs->phone = $request->phone;
            $hs->address = $request->address;
            $hs->uid = $user_id;
            $hs->customer_id = $customer->id;
            $hs->store_id = $customer->active_store;
            $hs->creator = $user_id;
            $hs->editor = $user_id;
            $hs->save();
            $activity = " Save Header Settings";
            $this->saveactivity($activity);
            Session::flash('success_message', 'Header Setting Created Successfully');
            return back();
        }
    }

    public function designsettings()
    {
        $urls = "design";
        return view('admin.design.header.settings')->with('urls', $urls);
    }

    public function homepage()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Slider Design', "landing-page.png", "/design/homepage/slider");

        $is_none = Design::select('hero_slider')->where('store_id', $store_id)
            ->where('hero_slider', 'null')
            ->first();

        $sliders = Slider::where('store_id', $store_id)->orderBy('position', 'ASC')->get();
        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', 'hero_slider')->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.hero_slider as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.hero_slider', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'slider')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Slider Design Page");
        return view('admin.design.homepage.slider')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'slider')
            ->with('is_none', $is_none)
            ->with('sliders', $sliders)
            ->with('design', $design);
    }

    public function filter(Request $request)
    {
        $urls = "design";

        $current_page = $request->current_page;
        $column = $request->column;

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $is_none = Design::select($column)->where('store_id', $store_id)
            ->where($column, 'null')
            ->first();

        if ($request->categoryfilter == 'all') {
            $design = Designlist::select(
                'designlists.*',
                "designs.$column as design_select"
            )
                ->leftJoin('designs', function ($join) use ($store_id, $column) {
                    $join->on("designs.$column", 'designlists.value')
                        ->where('designs.store_id', $store_id);
                })
                ->where('type', $current_page)->where('status', 'active')->get();
        } else {
            $design = Designlist::select(
                'designlists.*',
                "designs.$column as design_select"
            )->leftJoin('designs', function ($join) use ($store_id, $column) {
                $join->on("designs.$column", 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
                ->where('type', $current_page)
                ->whereRaw('FIND_IN_SET("' . $request->categoryfilter . '",category)')
                ->where('status', 'active')
                ->get();
        }
        $stts = $request->categoryfilter;
        $activity = " Filter Homepage $current_page Design By " . $stts;
        $this->saveactivity($activity);
        return view('admin.design.homepage.' . $current_page)
            ->with('store_id', $store_id)
            ->with('urls', $urls)
            ->with('is_none', $is_none)
            ->with('design', $design)
            ->with('stts', $stts);
    }

    public function additional_designs($column)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*check permission for access this feature*/
        if (!ModulusStatus($store_id, 111)) {
            return redirect()->back();
        }

        /*convert into string for activities and  top tools*/
        $string = ucwords(str_replace('_', ' ', $column));

        /*increment tools count*/
        topToolsCount("Homepage $string Design", "landing-page.png", "/design/homepage/$column");

        /*check is select none*/
        $is_none = Design::select("$column")->where('store_id', $store_id)
            ->where("$column", 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', $column)->first();

        /*get data from design list*/
        $design = Designlist::select(
            'designlists.*',
            "designs.$column as design_select"
        )
            ->leftJoin('designs', function ($join) use ($store_id, $column) {
                $join->on("designs.$column", 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', $column)
            ->where('designlists.status', 'active')
            ->get();

        /*save activities*/
        $this->saveactivity(" Access Homepage $string Design Page");

        /*dynamically return page*/
        return view("admin.design.homepage.$column")
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', $column)
            ->with('is_none', $is_none)
            ->with('design', $design);

    }

    public function common_designs($column)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*convert into string for activities and  top tools*/
        $string = ucwords(str_replace('_', ' ', $column));

        /*increment tools count*/
        topToolsCount("Homepage $string Design", "landing-page.png", "/design/homepage/$column");

        /*check is select none*/
        $is_none = Design::select("$column")->where('store_id', $store_id)
            ->where("$column", 'null')
            ->first();
        /*get data from design list*/
        $design = Designlist::select(
            'designlists.*',
            "designs.$column as design_select"
        )
            ->leftJoin('designs', function ($join) use ($store_id, $column) {
                $join->on("designs.$column", 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', $column)
            ->where('designlists.status', 'active')
            ->get();
        $active_design = DB::table('designs')->where('store_id', $store_id)->first();
        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', $column)->first();

        /*save activities*/
        $this->saveactivity(" Access Homepage $string Design Page");

        /*dynamically return page*/
        return view("admin.design.homepage.$column")
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', $column)
            ->with('is_none', $is_none)
            ->with('active_design', $active_design)
            ->with('design', $design);

    }

    public function saveslider123(Request $request)
    {
        $urls = "design";
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());
        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->slider == "null") {
                $design->hero_slider = null;
            } else {
                $design->hero_slider = $request->slider;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->slider == "null") {
                $design->hero_slider = null;
            } else {
                $design->hero_slider = $request->slider;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Homepage Slider Design Change " . $design->hero_slider;
        $this->saveactivity($activity);
        Session::flash('message', 'Slider Design Successfully !');
        return back();
    }

    public function changes_design(Request $request)
    {
        $urls = "design";
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $column = $request->column;

        $design = Design::where('store_id', $store_id)->first();
        if (!isset($design)) {
            $design = new Design;
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        if ($request->option == "null") {
            $design[$column] = null;
        } else {
            $design[$column] = $request->value;
        }
        $design->save();
        $data = $design;
        $this->saveactivity(" Homepage $column Design Change " . $design[$column]);
        return response()->json($data);
    }

    public function savebanner123(Request $request)
    {
        $urls = "design";
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());
        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->banner == "null") {
                $design->banner = null;
            } else {
                $design->banner = $request->banner;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->banner == "null") {
                $design->banner = null;
            } else {
                $design->banner = $request->banner;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Homepage Banner Design Save " . $design->banner;
        $this->saveactivity($activity);
        Session::flash('message', 'Banner Design Successfully !');
        return back();
    }

    public function homepagebanner()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Banner Design', "landing-page.png", "/design/homepage/banner");

        $is_none = Design::select('banner')->where('store_id', $store_id)
            ->where('banner', 'null')
            ->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.banner as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.banner', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'banner')
            ->where('designlists.status', 'active')
            ->get();

        $banners = Banner::where('store_id', $store_id)->get();

        $store_design = StoreDesign::where('store_id', $store_id)->where('type', 'banner')->first();
        if (!isset($store_design)) {
            $store_design = new StoreDesign;
        }
        $this->saveactivity(" Access Homepage Banner Design Page");
        return view('admin.design.homepage.banner')
            ->with('store_id', $store_id)
            ->with('store_design', $store_design)
            ->with('urls', $urls)
            ->with('type', 'banner')
            ->with('is_none', $is_none)
            ->with('banners', $banners)
            ->with('design', $design);
    }

    public function homepageBannerBottom()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Banner Design', "landing-page.png", "/design/homepage/banner-bottom");

        $is_none = Design::select('banner_bottom')->where('store_id', $store_id)
            ->where('banner_bottom', 'null')
            ->first();
        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "banner_bottom")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.banner_bottom as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.banner_bottom', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'banner_bottom')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Banner Design Page");
        return view('admin.design.homepage.banner_bottom')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'banner_bottom')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }


    public function homepagebannerBottomfilter(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());
        if ($request->categoryfilter == 'all') {
            $design = Designlist::where('type', 'banner_bottom')->where('status', 'active')->get();
        } else {
            $design = Designlist::where('type',
                'banner_bottom')->whereRaw('FIND_IN_SET("' . $request->categoryfilter . '",category)')->where('status',
                'active')->get();
        }
        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "banner_bottom")->first();

        $stts = $request->categoryfilter;
        $activity = " Homepage banner_bottom design filter By " . $stts;
        $this->saveactivity($activity);
        return view('admin.design.homepage.banner_bottom')->with('store_id', $store_id)->with('urls',
            $urls)->with('design', $design)->with('store_design', $storeDesign)->with('stts', $stts);
    }

    public function homepagefeaturecategory()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Feature Category Design', "landing-page.png", "/design/homepage/featurecategory");

        $is_none = Design::select('feature_category')->where('store_id', $store_id)
            ->where('feature_category', 'null')
            ->first();
        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "feature_category")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.feature_category as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.feature_category', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'feature_category')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Feature Category Design Page");
        return view('admin.design.homepage.feature_category')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'feature_category')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function savefeaturecategory(Request $request)
    {
        $urls = "design";
        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->fcat == "null") {
                $design->feature_category = null;
            } else {
                $design->feature_category = $request->fcat;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->fcat == "null") {
                $design->feature_category = null;
            } else {
                $design->feature_category = $request->fcat;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Feature Category Design " . $design->feature_category;
        $this->saveactivity($activity);
        Session::flash('message', 'Feature Category Design Successfully !');
        return back();
    }

    public function homepageproduct()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Product Design', "landing-page.png", "/design/homepage/slider");

        $is_none = Design::select('product')->where('store_id', $store_id)
            ->where('product', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "product")->first();

        $design = Designlist::select('designlists.*', 'designs.product as design_select')
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.product', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'product')
            ->where('designlists.status', 'active')
            ->get();

        $this->saveactivity(" Access Homepage Product Design Page");
        return view('admin.design.homepage.product')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'product')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveproduct(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->product == "null") {
                $design->product = null;
            } else {
                $design->product = $request->product;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->product == "null") {
                $design->product = null;
            } else {
                $design->product = $request->product;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Product Design " . $design->product;
        $this->saveactivity($activity);
        Session::flash('message', 'Product Design Successfully !');
        return back();
    }

    public function homepagetestimonial()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Testimonial Design', "landing-page.png", "/design/homepage/testimonial");

        $is_none = Design::select('testimonial')->where('store_id', $store_id)
            ->where('testimonial', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "testimonial")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.testimonial as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.testimonial', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'testimonial')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Testimonial Design Page");
        return view('admin.design.homepage.testimonial')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'testimonial')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function savetestimonial(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->testimonial == "null") {
                $design->testimonial = null;
            } else {
                $design->testimonial = $request->testimonial;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->testimonial == "null") {
                $design->testimonial = null;
            } else {
                $design->testimonial = $request->testimonial;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Testimonial Design " . $design->testimonial;
        $this->saveactivity($activity);
        Session::flash('message', 'Testimonial Design Saved Successfully !');
        return back();
    }

    public function homepageYoutube()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Youtube Design', "landing-page.png", "/design/homepage/youtube");

        $is_none = Design::select('youtube')->where('store_id', $store_id)
            ->where('youtube', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "youtube")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.youtube as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.youtube', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'youtube')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Youtube Design Page");

        return view('admin.design.homepage.youtube')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'youtube')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveYoutube(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->youtube == "null") {
                $design->youtube = null;
            } else {
                $design->youtube = $request->youtube;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->youtube == "null") {
                $design->youtube = null;
            } else {
                $design->youtube = $request->youtube;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Youtube Design " . $design->youtube;
        $this->saveactivity($activity);
        Session::flash('message', 'Youtube Design Saved Successfully !');
        return back();
    }

    public function homepageBrand()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Brand Design', "landing-page.png", "/design/homepage/brand");

        $is_none = Design::select('brand')->where('store_id', $store_id)
            ->where('brand', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "brand")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.brand as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.brand', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'brand')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Brand Design Page");

        return view('admin.design.homepage.brand')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'brand')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveBrand(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->brand == "null") {
                $design->brand = null;
            } else {
                $design->brand = $request->brand;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->brand == "null") {
                $design->brand = null;
            } else {
                $design->brand = $request->brand;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Brand Design " . $design->brand;
        $this->saveactivity($activity);
        Session::flash('message', 'Brand Design Saved Successfully !');
        return back();
    }

    public function homepageBlog()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Blog Design', "landing-page.png", "/design/homepage/blog");

        $is_none = Design::select('blog')->where('store_id', $store_id)
            ->where('blog', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "blog")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.blog as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.blog', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'blog')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Blog Design Page");

        return view('admin.design.homepage.blog')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'blog')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveBlog(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->blog == "null") {
                $design->blog = null;
            } else {
                $design->blog = $request->blog;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->blog == "null") {
                $design->blog = null;
            } else {
                $design->blog = $request->blog;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Blog Design " . $design->blog;
        $this->saveactivity($activity);
        Session::flash('message', 'Blog Design Saved Successfully!');
        return back();
    }

    public function homepagefooter()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Footer Design', "landing-page.png", "/design/homepage/footer");

        $is_none = Design::select('footer')->where('store_id', $store_id)
            ->where('footer', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "footer")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.footer as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.footer', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'footer')
            ->where('designlists.status', 'active')
            ->get();
        $activity = " Access Homepage Footer Design Page";
        $this->saveactivity($activity);
        return view('admin.design.homepage.footer')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'footer')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function savefooter(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            $design->footer = $request->footer;
            $design->save();
        } else {
            $design = new Design;
            $design->footer = $request->footer;
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Footer Design " . $design->footer;
        $this->saveactivity($activity);
        Session::flash('message', 'Footer Design Successfully !');
        return back();
    }

    /* new code start */
    public function homepageAnnouncement()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Announcement Design', "landing-page.png", "/design/homepage/announcement");

        $is_none = Design::select('announcement')->where('store_id', $store_id)
            ->where('announcement', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "announcement")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.announcement as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.announcement', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'announcement')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Announcement Design Page");

        return view('admin.design.homepage.announcement')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'announcement')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveAnnouncement(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->announcement == "null") {
                $design->announcement = null;
            } else {
                $design->announcement = $request->announcement;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->announcement == "null") {
                $design->announcement = null;
            } else {
                $design->announcement = $request->announcement;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Announcement Design " . $design->announcement;
        $this->saveactivity($activity);
        Session::flash('message', 'Announcement Design Saved Successfully !');
        return back();
    }

    public function homepageAbout()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage About Design', "landing-page.png", "/design/homepage/about");

        $is_none = Design::select('about')->where('store_id', $store_id)
            ->where('about', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "about")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.about as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.about', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'about')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage About Design Page");

        return view('admin.design.homepage.about')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'about')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveAbout(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->about == "null") {
                $design->about = null;
            } else {
                $design->about = $request->about;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->about == "null") {
                $design->about = null;
            } else {
                $design->about = $request->about;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage About Design " . $design->about;
        $this->saveactivity($activity);
        Session::flash('message', 'About Design Saved Successfully !');
        return back();
    }

    public function homepageNewsletter()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Newsletter Design', "landing-page.png", "/design/homepage/newsletter");

        $is_none = Design::select('newsletter')->where('store_id', $store_id)
            ->where('newsletter', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "newsletter")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.newsletter as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.newsletter', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'newsletter')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Newsletter Design Page");

        return view('admin.design.homepage.newsletter')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'newsletter')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }

    public function saveNewsletter(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->newsletter == "null") {
                $design->newsletter = null;
            } else {
                $design->newsletter = $request->newsletter;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->newsletter == "null") {
                $design->newsletter = null;
            } else {
                $design->newsletter = $request->newsletter;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Newsletter Design " . $design->newsletter;
        $this->saveactivity($activity);
        Session::flash('message', 'Newsletter Design Saved Successfully !');
        return back();
    }

    /* new code end */

    public function featureproduct()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Feature Product Design', "landing-page.png", "/design/homepage/feature_product");

        $is_none = Design::select('feature_product')->where('store_id', $store_id)
            ->where('feature_product', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "feature_product")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.feature_product as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.feature_product', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'feature_product')
            ->where('designlists.status', 'active')
            ->get();
        $this->saveactivity(" Access Homepage Feature feature_product Design Page");
        return view('admin.design.homepage.feature_product')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'feature_product')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }


    public function savefeatureproduct(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->feature_product == "null") {
                $design->feature_product = null;
            } else {
                $design->feature_product = $request->feature_product;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->feature_product == "null") {
                $design->feature_product = null;
            } else {
                $design->feature_product = $request->feature_product;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Feature Product Design " . $design->feature_product;
        $this->saveactivity($activity);
        Session::flash('message', 'Feature Product Design Successfully !');
        return back();
    }

    public function bestsellproduct()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Feature Product Design', "landing-page.png", "/design/homepage/feature_product");

        $is_none = Design::select('best_sell_product')->where('store_id', $store_id)
            ->where('best_sell_product', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "best_sell_product")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.best_sell_product as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.best_sell_product', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'best_sell_product')
            ->where('designlists.status', 'active')
            ->get();

        $this->saveactivity(" Access Homepage Best Sell Product Design Page");
        return view('admin.design.homepage.best_sell_product')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('is_none', $is_none)
            ->with('urls', $urls)
            ->with('type', 'best_sell_product')
            ->with('design', $design);
    }

    public function savebestsellproduct(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->best_sell_product == "null") {
                $design->best_sell_product = null;
            } else {
                $design->best_sell_product = $request->best_sell_product;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->best_sell_product == "null") {
                $design->best_sell_product = null;
            } else {
                $design->best_sell_product = $request->best_sell_product;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Best Sell Product Design " . $design->best_sell_product;
        $this->saveactivity($activity);
        Session::flash('message', 'Best Sell Product Design Successfully !');
        return back();
    }

    public function recentaddproduct()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Homepage Recent Added  Product Design', "landing-page.png",
            "/design/homepage/new_arrival");

        $is_none = Design::select('new_arrival')->where('store_id', $store_id)
            ->where('new_arrival', 'null')
            ->first();

        $storeDesign = StoreDesign::where('store_id', $store_id)->where('type', "new_arrival")->first();

        $design = Designlist::select(
            'designlists.*',
            'designs.new_arrival as design_select'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.new_arrival', 'designlists.value')
                    ->where('designs.store_id', $store_id);
            })
            ->where('designlists.type', 'new_arrival')
            ->where('designlists.status', 'active')
            ->get();

        $this->saveactivity(" Access Homepage Recent Add Product Design Page");
        return view('admin.design.homepage.new_arrival_product')
            ->with('store_id', $store_id)
            ->with('store_design', $storeDesign)
            ->with('urls', $urls)
            ->with('type', 'new_arrival')
            ->with('is_none', $is_none)
            ->with('design', $design);
    }


    public function saverecentaddproduct(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            if ($request->new_arrival == "null") {
                $design->new_arrival = null;
            } else {
                $design->new_arrival = $request->new_arrival;
            }
            $design->save();
        } else {
            $design = new Design;
            if ($request->new_arrival == "null") {
                $design->new_arrival = null;
            } else {
                $design->new_arrival = $request->new_arrival;
            }
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Recent Add Product Design " . $design->new_arrival;
        $this->saveactivity($activity);
        Session::flash('message', 'Recent Added Product Design Successfully !');
        return back();
    }

    public function design_invoice()
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Invoice Template', "bill-2.png",
            "/design/invoice");
        $designs = Designlist::select(
            'designlists.image',
            'designlists.name',
            'designlists.value',
            'designlists.id',
            'designs.invoice',
            'inv_purchase.status'
        )
            ->leftJoin('designs', function ($join) use ($store_id) {
                $join->on('designs.invoice', '=', 'designlists.value')
                    ->where('designs.store_id', '=', $store_id);
            })
            ->leftJoin('invoicepurchases as inv_purchase', function ($join) use ($store_id) {
                $join->on('inv_purchase.invoice_id', 'designlists.id')
                    ->where('inv_purchase.store_id', $store_id);
            })
            ->where('designlists.type', 'invoice')
            ->where('designlists.status', 'active')
            ->get();
//            dd(json_encode($designs));
        $activity = " Access Invoice Design Page";
        $this->saveactivity($activity);
        return view('admin.design.invoice.index')
            ->with('store_id', $store_id)
            ->with('urls', $urls)
            ->with('designs', $designs);
    }

    public function invoice_search(Request $request)
    {

        $urls = "design";
        $keyword = $request->keyword;

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        /*increment tools count*/
        topToolsCount('Invoice Template', "bill-2.png", "/design/invoice");

        $design = Designlist::where('type', 'invoice')
            ->where('status', 'active')
            ->where('name', 'LIKE', '%' . $keyword . '%')
            ->get();
        $activity = " Access Homepage Invoice Design Page";
        $this->saveactivity($activity);
        return view('admin.design.invoice.index')
            ->with('store_id', $store_id)
            ->with('urls', $urls)
            ->with('keyword', $keyword)
            ->with('design', $design);
    }

    public function saveinvoice(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            $design->invoice = $request->invoice;
            $design->save();
        } else {
            $design = new Design;
            $design->invoice = $request->invoice;
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $activity = " Change Homepage Invoice Design " . $design->invoice;
        $this->saveactivity($activity);
        Session::flash('message', 'Invoice Design Successfully !');
        return back();
    }

    public function changeinvoice(Request $request)
    {
        $urls = "design";

        /*extract user_id, user_type, store_id,customer, customer_id*/
        extract(getUserData());

        $desig = Design::where('store_id', $store_id)->first();
        if (isset($desig)) {
            $design = Design::find($desig->id);
            $design->invoice = $request->invoice;
            $design->save();
        } else {
            $design = new Design;
            $design->invoice = $request->invoice;
            $design->uid = $user_id;
            $design->customer_id = $customer_id;
            $design->store_id = $store_id;
            $design->creator = $user_id;
            $design->editor = $user_id;
            $design->save();
        }
        $data = $design;
        $activity = " Change Homepage Invoice Design " . $design->invoice;
        $this->saveactivity($activity);
        return response()->json($data);
    }

    public function changesliderssstatus(Request $request)
    {
        if ($request->text2 == '') {
            Session::flash('message', 'Please Select Slider');
            return back();
        }
        if ($request->action == 'select') {
            Session::flash('message', 'Please Select a Option');
            return back();
        }

        if ($request->action == 'active') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Slider::find($ids);
                    $product->status = 'active';
                    $product->save();
                }
            }
            $activity = " Change Slider Status ";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Active Slider');
            return back();
        }
        if ($request->action == 'deactive') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Slider::find($ids);
                    $product->status = 'deactive';
                    $product->save();
                }
            }
            $activity = " Change Slider Status ";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Deactive Slider');
            return back();
        }
        if ($request->action == 'delete') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Slider::find($ids);
                    $product->delete();
                }
            }
            $activity = " Slider Delete ";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Deleted Slider');
            return back();
        }
    }

    public function changebannerssstatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text2' => 'required',
            'action' => 'required|in:active,deactive,delete',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $id = explode(',', $request->text2);

        if ($request->action == 'active') {
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Banner::find($ids);
                    $product->status = 'active';
                    $product->save();
                }
            }
            $activity = " Change Banner Status ";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Active Banner');
            return back();
        }
        if ($request->action == 'deactive') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Banner::find($ids);
                    $product->status = 'deactive';
                    $product->save();
                }
            }
            $activity = " Change Banner Status ";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Deactive Banner');
            return back();
        }
        if ($request->action == 'delete') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $product = Banner::find($ids);
                    $product->delete();
                }
            }
            $activity = " Delete Banner";
            $this->saveactivity($activity);
            Session::flash('message', 'Successfully Deleted Banner');
            return back();
        }
    }

    public function store_design_save(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'string',
                'title_color' => 'string',
                'subtitle' => 'string',
                'subtitle_color' => 'string',
                'button' => 'string',
                'button_color' => 'string',
                'button_bg_color' => 'string',
                'button1' => 'string',
                'button1_color' => 'string',
                'button1_bg_color' => 'string',
                'is_buy_now_cart' => 'string',
                'link' => 'string',
                'bg_image' => 'string',
                'store_id' => 'required|numeric',
                'type' => 'required|string'
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }


            if (!is_null($request->is_buy_now_cart) && $request->is_buy_now_cart == "on") {
                $is_buy_now_cart = 1;
                $is_buy_now_cart1 = 0;
            } else {
                $is_buy_now_cart = 0;
                $is_buy_now_cart1 = 1;
            }


            if (
                isset($validator->validated()['title']) ||
                isset($validator->validated()['subtitle']) ||
                isset($validator->validated()['button']) ||
                isset($validator->validated()['button1']) ||
                isset($validator->validated()['link'])
            ) {
                $store_design = StoreDesign::firstOrNew([
                    'store_id' => $validator->validated()['store_id'],
                    'type' => $validator->validated()['type']
                ]);

                if ($validator->validated()['title'] != "") {
                    $store_design->title = $validator->validated()['title'] ?? null;
                    $store_design->title_color = $validator->validated()['title_color'] ?? null;
                } else {
                    $store_design->title = null;
                    $store_design->title_color = null;
                }

                if ($validator->validated()['subtitle'] != "") {
                    $store_design->subtitle = $validator->validated()['subtitle'] ?? null;
                    $store_design->subtitle_color = $validator->validated()['subtitle_color'] ?? null;
                } else {
                    $store_design->subtitle = null;
                    $store_design->subtitle_color = null;
                }

                if ($validator->validated()['button'] != "") {
                    $store_design->button = $validator->validated()['button'] ?? null;
                    $store_design->button_color = $validator->validated()['button_color'] ?? null;
                    $store_design->button_bg_color = $validator->validated()['button_bg_color'] ?? null;
                } else {
                    $store_design->button = null;
                    $store_design->button_color = null;
                    $store_design->button_bg_color = null;
                }
                if ($validator->validated()['button1'] != "") {
                    $store_design->button1 = $validator->validated()['button1'] ?? null;
                    $store_design->button1_color = $validator->validated()['button1_color'] ?? null;
                    $store_design->button1_bg_color = $validator->validated()['button1_bg_color'] ?? null;
                } else {
                    $store_design->button1 = null;
                    $store_design->button1_color = null;
                    $store_design->button1_bg_color = null;
                }

                if ($request->input('bg_image')) {
//                    $image = $request->file('bg_image');
//                    $validation = imageValidation($image, $validator->validated()['store_id']);
//                    if ($validation) {
//                        return back()->with('warning', $validation);
//                    }
//
//                    $imageUploadPath = 'assets/images/design/';
//                    $imageName = updateFile($image, $imageUploadPath, $store_design->bg_image);
                    $store_design->bg_image = getLibraryImagePath($request->bg_image);
                }

                $store_design->link = $validator->validated()['link'] ?? null;

                $store_design->is_buy_now_cart = $is_buy_now_cart ?? 0;
                $store_design->is_buy_now_cart1 = $is_buy_now_cart1 ?? 1;
                $store_design->save();
            }
            return redirect()->back()->with('success', 'Store design saved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to save store design: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Change design header position
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeDesignHeaderPosition(Request $request)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'] ?? "";
        $name = $request->name ?? "";
        $position = $request->position ?? "";

        if ($store_id && !empty($name) && !empty($position)) {
            $designPosition = DesignPosition::updateOrCreate(
                ['name' => $name, 'store_id' => $store_id], // Find by both name and store_id
                ['position' => $position] // Update or create with this position
            );

            if ($designPosition) {
                return response()->json(["status" => true, "message" => "Position save successfully!"]);
            }
            return response()->json(["status" => false, "message" => "Position not save!"]);
        }

        return response()->json(["status" => false, "message" => "Request data missing!"]);

    }


    /**
     *
     * Save checkout form field data for store
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveDesignCheckoutForm(Request $request)
    {
        $userData = getUserData();
        $store_id = $userData['store_id'] ?? "";
        $checkbox = $request->checkbox ?? "";

        if ($store_id && !is_null($checkbox) && count($checkbox)) {
            foreach ($checkbox as $key => $val) {
                $CheckoutForm = CheckoutForm::updateOrCreate(
                    ['name' => $key, 'store_id' => $store_id], // Find by both name and store_id
                    ['status' => $val] // Update or create with this position
                );
            }

            if ($CheckoutForm) {
                return redirect()->back()->with('success', 'Checkout form field save successfully!');
            }

            return redirect()->back()->with('error', 'Checkout form field not save!');
        }

        return redirect()->back()->with('error', 'Request data missing!');
    }


    /***
     * Get Checkout form field data by store id
     *
     * @param $store
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDesignCheckoutForm($store)
    {
        if (!is_null($store) && !empty($store)) {
            $storeData = CheckoutForm::where("store_id", $store)->select("id", "name", "status")->get();

            if (count($storeData) == 0 || empty($storeData)) {
                $checkbox = [
                    "name" => 1,
                    "phone" => 1,
                    "email" => 0,
                    "address" => 1,
                    "note" => 0,
                    "district" => 0,
                    "language" => 0,
                ];

                foreach ($checkbox as $key => $val) {
                    CheckoutForm::updateOrCreate(
                        ['name' => $key, 'store_id' => $store], // Find by both name and store_id
                        ['status' => $val] // Update or create with this position
                    );
                }
            }

            return response()->json(["status" => true, "message" => "Success!", "data" => $storeData]);
        }

        return response()->json(["status" => false, "message" => "Store id missing!"]);
    }


    /**
     * Header design menu delete
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteHeaderDesignMenu($id)
    {
        try {
            if (is_null($id) || empty($id)) {
                return redirect()->back()->with('error', 'Record ID missing!');
            }

            Menu::where("id", $id)->delete();
            return redirect()->back()->with('Success', 'Menu deleted successfully!');

        } catch (\Exception $exception) {
            return redirect()->back()->with('error', 'Something went wrong!');
        }
    }


}
