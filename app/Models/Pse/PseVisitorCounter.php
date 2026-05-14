<?php

namespace App\Models\Pse;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PseVisitorCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip',
        'appr_id',
        'product_id',
        'store_id',
        'store_url'
    ];
}
