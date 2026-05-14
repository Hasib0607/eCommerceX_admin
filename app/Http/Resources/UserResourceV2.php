<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResourceV2 extends JsonResource
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
            "id" => $this->id,
            "name" => $this->name,
            'image' => $this->imageURL($this->image),
            "social_img" => $this->imageURL($this->social_img),
            "email" => $this->email,
            "type" => $this->type,
            "phone" => $this->phone,
            "username" => $this->username,
            "otp" => $this->otp,
            "address" => $this->address,
            "referral" => $this->referral,
            "refer_by" => $this->refer_by,
            "referral_commission" => $this->referral_commission,
            "total_commission" => $this->total_commission,
            "affiliate_info" => $this->affiliate_info
        ];

    }


    public function imageURL($img)
    {
        $appURL = env('APP_URL');
        if (!empty($img) || !is_null($img)) {
            return asset("/assets/images/img") . "/" . $img;
        }

        return asset("/user.png");
    }


}
