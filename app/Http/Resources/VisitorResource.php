<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->visitor_name,
            'email' => $this->visitor_email,
            'phone' => $this->visitor_phone,
            'is_register' => $this->is_register,
            'session_token' => $this->session_token,
            'image' => $this->image,
        ];
    }


}
