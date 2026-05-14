<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcceptedPseProductRequest extends Model
{
    use HasFactory;
    protected $table = 'accepted_pse_product_requests';
    protected $fillable = [
        'product_id',
        'category_id',
        'position',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
