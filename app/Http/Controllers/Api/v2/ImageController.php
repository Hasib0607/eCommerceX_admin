<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Category;
use App\Models\Store;
use App\Models\Menu;
use App\Models\Slider;
use App\Models\Product;
use App\Models\Headersetting;
use App\Models\Staff;
use App\Models\Banner;
use App\Models\Testimonial;
use App\Models\Coupon;
use App\Models\Design;
use App\Models\Veriant;
use App\Models\Offer;
use App\Models\Mobileapp;
use App\Models\Campaign;
use App\Models\Plan;
use App\Models\HomePae;
use App\Models\Template;
use App\Models\Temposition;
use App\Models\Tricket;
use App\Models\Supersetting;
use App\Models\Notification;
use Illuminate\Support\Str;
use App\Models\Page;
use App\Models\Brand;
use Auth;
use App\Models\Review;
use Carbon\Carbon;

class ImageController extends Controller
{
    public function saveslider(Request $request)
    {
        $slider = Slider::find($request->slider);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/slider/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savebanner(Request $request)
    {
        $slider = Banner::find($request->banner);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/banner/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savetestimonials(Request $request)
    {
        $slider = Testimonial::find($request->testimonial);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/testimonials/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savehs(Request $request)
    {
        $slider = Headersetting::find($request->hs);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/setting/' . $safeName, $file);
            $slider->logo = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function saveuserimage(Request $request)
    {
        $slider = User::find($request->user);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/img/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savetoken(Request $request)
    {
        $slider = Tricket::find($request->token);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/token/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savemapp(Request $request)
    {
        $slider = Mobileapp::find($request->mapp);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/category/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savebrand(Request $request)
    {
        $slider = Brand::find($request->brand);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/brand/' . $safeName, $file);
            $slider->image = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function savecat(Request $request)
    {
        $slider = Category::find($request->category);
        if ($request->image) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/category/' . $safeName, $file);
            $slider->banner = $safeName;
        }
        $slider->save();
        return ["image" => $safeName];
    }

    public function saveproduct(Request $request)
    {
        if (isset($request->image)) {
            $img = substr($request->image, strpos($request->image, ","));
            $file = base64_decode($img);
            $safeName = Str::random(10) . '.' . 'jpg';
            // $file->storeAs('img',$safeName);
            $success = file_put_contents(public_path() . '/assets/images/product/' . $safeName, $file);
        }
        return ["image" => $safeName];
    }

}
