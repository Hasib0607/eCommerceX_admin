<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'sender_type' => $this->sender_type,
            'last_message' => $this->last_message,
            'seen_status' => $this->seen_status,
            'status' => $this->status,
            'type' => $this->type,
            'lang' => $this->lang,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'client' => $this->getVisitorInfo($this->visitor),
            'media' => $this->getMedia($this->id),
        ];
    }

    // Get cliet info
    public function getVisitorInfo($visitor)
    {
        if (Auth::check() && (Auth::user()->type == "superadmin" || Auth::user()->type == "superstaff")) {
            if (!is_null($visitor->user_id) && $visitor->is_register) {
                $user = User::where('id', $visitor->user_id)->first();

                if (!is_null($user)) {
                    $resData = [];
                    $user_type = $user->type;
                    $user_id = $user->id;

                    $store_id = "";
                    if ($user_type == 'admin' || $user_type == 'dropshipper' || $user_type == 'affiliate') {
                        $customer = Customer::where('uid', $user_id)->first();
                        $store_id = $customer->active_store ?? "";
                    } elseif ($user_type == 'staff') {
                        $staff = Staff::where('uid', $user_id)->first();
                        $store_id = $staff->store_id ?? "";
                    }

                    $resData['user_type'] = $user_type;

                    $store = Store::where('id', $store_id)->first();

                    if (!is_null($store)) {
                        $resData['store_name'] = $store->name;
                        $resData['website_url'] = $store->url;

                        $plan = Plan::where('id', $store->plan_id)->first();

                        if (!is_null($plan)) {
                            $conversionsCurrency = conversionsCurrency($plan->price, $plan->currency_id, $store_id);
                            $plan->price = $conversionsCurrency['amount'];
                            $symbol = $conversionsCurrency['symbol'];
                            $code = $conversionsCurrency['code'];

                            $resData['plan_name'] = $plan->name;
                            $resData['plan_price'] = $plan->price;
                            $resData['plan_purchase_date'] = $store->purchase_date;
                            $resData['plan_expiry_date'] = $store->expiry_date;
                            $resData['upcoming_plan_month'] = $store->upcoming_plan_month;
                            $resData['upcoming_plan_expiry_date'] = $store->upcoming_plan_expiry_date;
                            $resData['symbol'] = $symbol;
                            $resData['code'] = $code;

                            if (isset($store->upcoming_plan_id)) {
                                $upcoming_plan = Plan::where('id', $store->upcoming_plan_id)->first();

                                if (!is_null($upcoming_plan)) {
                                    $resData['upcoming_plan'] = $upcoming_plan->name;
                                }
                            }
                        }
                    }

                    return $resData;
                }
            }
        }

        return null;
    }

    // Get conversation media
    public function getMedia($id)
    {
        if (Auth::check() && (Auth::user()->type == "superadmin" || Auth::user()->type == "superstaff")) {
            if ($id) {
                // Query messages with non-null file_url for a specific conversation_id
                $messages = ChatMessage::where('conversation_id', $id)
                    ->whereNotNull('file_url')  // Ensure file_url is not null
                    ->where('file_url', '<>', '')  // Ensure file_url is not empty
                    ->orderBy('id', 'desc')
                    ->paginate(50);

                $fileURLs = [];

                foreach ($messages as $message) {
                    // Assuming $message->file_url is a string or array of file URLs
                    $files = is_array($message->file_url) ? $message->file_url : explode(',', $message->file_url);

                    foreach ($files as $file) {
                        // Assuming your file URLs are relative paths stored in database, prepend asset() with your file path
                        $fileURLs[] = asset('/chat/chatFile/' . $file);
                    }
                }

                return $fileURLs;
            }
        }

        return [];
    }

}
