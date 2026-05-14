<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
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
            "title" => $this->title,
            "details" => $this->details,
            "image" => $this->imageURL($this->image),
            "price" => $this->price,
            "rating" => $this->rating,
            "status" => $this->status,
        ];
    }

    /**
     * Get the image url
     *
     * @param $img
     * @return string|null
     */
    public function imageURL($img)
    {
        $appURL = env('APP_URL');
        if (!empty($img) || !is_null($img)) {
            return $appURL . "/modulus/" . $img;
        }

        return null;
    }
}
