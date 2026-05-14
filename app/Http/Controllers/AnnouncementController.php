<?php

namespace App\Http\Controllers;

use App\Models\AdminBlogType;
use App\Models\Announcement;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AnnouncementController extends Controller
{
    private function resolveStoreId(): int
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return 0;
        }

        if (($authUser->type ?? '') === 'admin') {
            $customer = Customer::where('uid', $authUser->id)->first();
            return (int) ($customer->active_store ?? 0);
        }

        $staff = Staff::where('uid', $authUser->id)->first();
        return (int) ($staff->store_id ?? 0);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id = null)
    {
        $userData = getUserData();
        $store_id = $userData["store_id"] ?? "";

        $announcements = Announcement::where("user_id", Auth::id())->where("store_id", $store_id)->get();
        $data['announcements'] = $announcements;

        if (!is_null($id)) {
            $announcement = Announcement::where('id', $id)->where("store_id", $store_id)->first();
            $data['announcement'] = $announcement;
        }

        return view('admin.announcement.index', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $userData = getUserData();
        $store_id = $userData["store_id"] ?? "";

        $msg = "Announcement updated successfully";

        $announcement = Announcement::where('id', $request->id)->first();
        if (!isset($announcement)) {
            $announcement = new Announcement();
            $announcement->user_id = Auth::user()->id ?? null;
            $announcement->store_id = $store_id;
            $announcement->status = "1";
            $msg = "Announcement created successfully";
        }

        $announcement->announcement = $request->announcement;
        $announcement->save();

        Session::flash("success", $msg);
        return redirect()->route('admin.announcement.index');
    }


    /**
     *
     * Change Announcement status
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function changeAnnouncementStatus(Request $request)
    {
        if ($request->text2 == '') {
            Session::flash('error', 'Please Select Announcement');
            return back();
        }
        if ($request->action == 'select') {
            Session::flash('error', 'Please Select a Option');
            return back();
        }

        if ($request->action == 'active') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $announcement = Announcement::find($ids);
                    $announcement->status = 1;
                    $announcement->save();
                }
            }

            Session::flash('success', 'Successfully Active Announcement');
            return back();
        }
        if ($request->action == 'deactive') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $announcement = Announcement::find($ids);
                    $announcement->status = 0;
                    $announcement->save();
                }
            }

            Session::flash('success', 'Successfully Deactive Announcement');
            return back();
        }
        if ($request->action == 'delete') {
            $id = explode(',', $request->text2);
            if (isset($id) && count($id) > 0) {
                foreach ($id as $ids) {
                    $announcement = Announcement::find($ids);
                    $announcement->delete();
                }
            }

            Session::flash('success', 'Successfully Deleted Announcement');
            return back();
        }
    }

    /**
     *
     * Single Announcement status change
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function singleAnnouncementStatusChange(Request $request)
    {
        $id = $request->id;
        $announcement = Announcement::find($id);
        if (isset($announcement) && $announcement->status == '1') {
            $announcement->status = '0';
        } else {
            $announcement->status = "1";
        }
        $announcement->save();
        $data = $announcement;

        return response()->json($data);
    }


    /**
     * Announcement type delete
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteAnnouncement(Request $request)
    {
        $id = $request->id ?? "";
        if (is_null($id) || empty($id)) {
            Session::flash("error", "Data Not Found");
            return back();
        }
        $announcement = Announcement::find($id);
        if (isset($announcement)) {
            $announcement->delete();

            Session::flash("success", "Data deleted successfully");
            return back();
        }

        Session::flash("error", "Data Not Deleted!");
        return back();
    }

    public function indexApi(): JsonResponse
    {
        $storeId = $this->resolveStoreId();
        $announcements = Announcement::query()
            ->where('store_id', $storeId)
            ->latest('id')
            ->get(['id', 'announcement', 'status', 'created_at', 'updated_at']);

        return response()->json([
            'items' => $announcements,
        ]);
    }

    public function storeApi(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'announcement' => ['required', 'string', 'max:255'],
        ]);

        $announcement = new Announcement();
        $announcement->announcement = $payload['announcement'];
        $announcement->status = 1;
        $announcement->user_id = Auth::id();
        $announcement->store_id = $this->resolveStoreId();
        $announcement->save();

        return response()->json([
            'item' => $announcement,
        ], 201);
    }

    public function updateApi(Request $request, int $id): JsonResponse
    {
        $payload = $request->validate([
            'announcement' => ['required', 'string', 'max:255'],
        ]);

        $announcement = Announcement::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id);

        $announcement->announcement = $payload['announcement'];
        $announcement->save();

        return response()->json([
            'item' => $announcement,
        ]);
    }

    public function destroyApi(int $id): JsonResponse
    {
        $announcement = Announcement::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id);
        $announcement->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function toggleStatusApi(int $id): JsonResponse
    {
        $announcement = Announcement::query()
            ->where('store_id', $this->resolveStoreId())
            ->findOrFail($id);

        $announcement->status = (int) !$announcement->status;
        $announcement->save();

        return response()->json([
            'item' => $announcement,
        ]);
    }

    public function bulkActionApi(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:active,deactive,delete'],
        ]);

        $query = Announcement::query()
            ->where('store_id', $this->resolveStoreId())
            ->whereIn('id', $payload['ids']);

        if ($payload['action'] === 'active') {
            $query->update(['status' => 1]);
        } elseif ($payload['action'] === 'deactive') {
            $query->update(['status' => 0]);
        } else {
            $query->delete();
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
