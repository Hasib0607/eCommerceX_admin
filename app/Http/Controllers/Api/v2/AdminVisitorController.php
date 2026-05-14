<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\AdminVisitor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminVisitorController extends Controller
{

    public function storeVisitorData(Request $request)
    {
        try {
            if (isset($request->store_url) && !empty($request->store_url)) {
                $adminVisitor = new AdminVisitor();
                $adminVisitor->store_id = $request->store_id ?? NULL;
                $adminVisitor->store_url = $request->store_url ?? NULL;
                $adminVisitor->user_id = $request->user_id ?? NULL;
                $adminVisitor->page_url = $request->page_url ?? NULL;
                $adminVisitor->page_title = $request->page_title ?? NULL;
                $adminVisitor->refer_page_url = $request->refer_page_url ?? NULL;
                $adminVisitor->ip = $request->ip ?? NULL;
                $adminVisitor->device = $request->device ?? NULL;
                $adminVisitor->mac = $request->mac ?? NULL;
                $adminVisitor->os = $request->os ?? NULL;
                $adminVisitor->browser = $request->browser ?? NULL;
                $adminVisitor->country_code = $request->country_code ?? NULL;
                $adminVisitor->country_name = $request->country_name ?? NULL;
                $adminVisitor->state = $request->state ?? NULL;
                $adminVisitor->city = $request->city ?? NULL;
                $adminVisitor->zip_code = $request->zip_code ?? NULL;
                $adminVisitor->location = $request->location ?? NULL;
                $adminVisitor->latitude = $request->latitude ?? NULL;
                $adminVisitor->longitude = $request->longitude ?? NULL;
                $adminVisitor->category_id = $request->category_id ?? NULL;
                $adminVisitor->product_id = $request->product_id ?? NULL;
                $adminVisitor->visit_time = $request->visit_time ?? NULL;
                $adminVisitor->time_zone = $request->time_zone ?? NULL;
                $adminVisitor->save();
            }

            return sendResponse("Successful", ["data" => $adminVisitor ?? NULL]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }

    public function updateVisitorData(Request $request)
    {
        try {
            if (isset($request->id) && !empty($request->id)) {
                $adminVisitor = AdminVisitor::where("id", $request->id)->first();
                if (isset($adminVisitor)) {
                    $adminVisitor->exit_time = $request->exit_time ?? NULL;
                    $adminVisitor->save();

                    return sendResponse("Successfully updated");
                }
            }

            return sendError("Record not updated!");
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }


}
