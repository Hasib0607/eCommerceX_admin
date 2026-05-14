<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductLayout extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'store_id', 'layout_design_id', 'text', 'link', 'button', 'type', 'position'];

    public function design()
    {
        return $this->belongsTo(LayoutDesign::class, 'layout_design_id', 'id');
    }
}
