<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LayoutDesignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $product = [
            "id" => $this->id,
            "currency_id" => $this->currency_id,
            "name" => $this->name,
            "description" => $this->description,
            "regular_price" => $this->regular_price,
            "discount_type" => $this->discount_type,
            "promotional_price" => $this->promotional_price,
            "discount_product" => $this->discount_product,
            "prev_discount" => $this->prev_discount,
            "tax_type" => $this->tax_type,
            "tax_rate" => $this->tax_rate,
            "quantity" => $this->quantity,
            "volume" => $this->volume,
            "unit" => $this->unit,
            "stock_status" => $this->stock_status,
            "pre_order_note" => $this->pre_order_note,
            "seo_keywords" => $this->seo_keywords,
            "weight" => $this->weight,
            "video_link" => $this->video_link,
            "shipping_fee" => $this->shipping_fee,
            "images" => $this->images,
            "gallery_image" => $this->gallery_image,
            "category" => $this->category,
            "subcategory" => $this->subcategory,
            "tags" => $this->tags,
            "position" => $this->position,
            "status" => $this->status,
            "best_sell" => $this->best_sell,
            "feature" => $this->feature,
            "uid" => $this->uid,
            "customer_id" => $this->customer_id,
            "store_id" => $this->store_id,
            "creator" => $this->creator,
            "editor" => $this->editor,
            "brand" => $this->brand,
            "supplier" => $this->supplier,
            "cost" => $this->cost,
            "pse" => $this->pse,
            "pse_req_date" => $this->pse_req_date,
            "pse_status" => $this->pse_status,
            "pse_cat_id" => $this->pse_cat_id,
            "barcode" => $this->barcode,
            "ask_price" => $this->ask_price,
            "expiry_date" => $this->expiry_date,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "commission" => $this->commission,
            "SKU" => $this->SKU,
            "product_link" => $this->product_link,
            "symbol" => $this->symbol,
            "code" => $this->code,
        ];

        $layouts = $this->getLayout($this->layout);
        $layout = array_merge($product, $layouts);
        return $layout;
    }


    public function getLayout($arr)
    {
        $layout = [];
        $needle = 'product_';
        foreach ($arr as $index => $value) {
            if (strpos($value->type, $needle) === false) {
                $layout['layout'][$index] = $value;
            } else {
                $layout[$value->type] = $value;
            }
        }
        return $layout;
    }


}
