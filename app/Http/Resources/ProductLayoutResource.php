<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductLayoutResource extends JsonResource
{
    protected $product_images;

    public function __construct($resource, $images = [])
    {
        parent::__construct($resource);
        $this->product_images = $images;
    }

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
            "product_id" => $this->product_id,
            "store_id" => $this->store_id,
            "layout_design_id" => $this->layout_design_id,
            "text" => $this->text,
            "link" => $this->getImageLink($this->link, $this->type),
            "button" => $this->button,
            "type" => $this->type,
            "position" => $this->position,
            "design" => $this->design,
        ];
    }


    public function getImageLink($link, $type)
    {
        if ($type == "image") {
            $link = array_filter(explode(',', $link));
            return array_map(fn($img) => getPath($img, 'assets/images/product'), $link);
        }
        if ($type == "product_image") {
            return $this->product_images;
        }

        return $link;

    }


}
