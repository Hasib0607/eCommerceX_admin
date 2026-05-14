<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BuyModuleResource extends JsonResource
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
            "modulus_id" => $this->modulus_id,
            "price" => $this->price,
            "type" => $this->type,
            "start_date" => $this->start_date,
            "end_date" => $this->end_date,
            "sms_count" => $this->sms_count,
            "status" => $this->status,
            "module" => new ModuleResource($this->module),
        ];
    }
}
