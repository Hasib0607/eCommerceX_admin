<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuyModulus extends Model
{
    use HasFactory;

    protected $fillable = [
        'modulus_id',
        'store_id',
    ];


    public function module()
    {
        return $this->belongsTo(Modulus::class, "modulus_id");
    }

}
