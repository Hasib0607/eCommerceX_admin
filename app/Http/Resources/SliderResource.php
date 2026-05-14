<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SliderResource extends JsonResource
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
            'image' => getPath($this->image, 'assets/images/slider'),
            'subimage' => $this->subimage ? getPath($this->subimage, 'assets/images/slider') : null,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'button' => $this->button,
            'link' => $this->link,
            'color' => $this->color,
            'subtitle_color' => $this->subtitle_color,
            'button_color' => $this->button_color,
            'position' => $this->position,
            'status' => $this->status,
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
