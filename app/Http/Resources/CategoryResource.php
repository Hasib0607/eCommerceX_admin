<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'slug' => $this->slug,
            'parent' => $this->parent,
            'market_id' => $this->market_id,
            'banner' => getPath($this->banner, 'assets/images/category'),
            'icon' => getPath($this->icon, 'assets/images/icon'),
            'status' => $this->status,
            'position' => $this->position,
            'uid' => $this->uid,
            'customer_id' => $this->customer_id,
            'store_id' => $this->store_id,
            'creator' => $this->creator,
            'editor' => $this->editor,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'total_products' => $this->total_products ?? 0,
            'subcategories' => SubcategoryResource::collection($this->whenLoaded('subcategories')),
        ];
    }
}
