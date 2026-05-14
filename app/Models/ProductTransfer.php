<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTransfer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function branchFrom()
    {
        return $this->belongsTo(Branch::class, 'from_branch', 'id');
    }

    public function branchTo()
    {
        return $this->belongsTo(Branch::class, 'to_branch', 'id');
    }


}
