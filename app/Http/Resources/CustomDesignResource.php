<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomDesignResource extends JsonResource
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
            'title' => $this->title,
            'title_color' => $this->title_color,
            'subtitle' => $this->subtitle,
            'subtitle_color' => $this->subtitle_color,
            'button' => $this->button,
            'button_color' => $this->button_color,
            'button_bg_color' => $this->button_bg_color,
            'button1' => $this->button1,
            'button1_color' => $this->button1_color,
            'button1_bg_color' => $this->button1_bg_color,
            'link' => $this->link,
            'bg_image' => getPath($this->bg_image, 'assets/images/design'),
            'image_description' => $this->image_description,
            'is_buy_now_cart' => $this->is_buy_now_cart,
            'is_buy_now_cart1' => $this->is_buy_now_cart1,
            'type' => $this->type,
        ];
    }
}
