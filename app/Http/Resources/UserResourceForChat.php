<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResourceForChat extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'image' => $this->imageURL($this->image),
        ];
    }


    public function imageURL($img)
    {
        $appURL = env('APP_URL');
        if (!empty($img) || !is_null($img)) {
            return $appURL . "/assets/images/img/" . $img;
        }

        return $appURL . "/fav-icon.png";
    }


}
