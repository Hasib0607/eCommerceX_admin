<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TestimonialResource extends JsonResource
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
            'occupation' => $this->occupation,
            'image' => getPath($this->image, 'assets/images/testimonials'),
            'feedback' => $this->feedback,
            'status' => $this->status,
            'position' => $this->position,
            'uid' => $this->uid,
            'customer_id' => $this->customer_id,
            'store_id' => $this->store_id,
            'creator' => $this->creator,
            'editor' => $this->editor,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
