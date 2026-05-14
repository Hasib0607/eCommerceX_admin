<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModulusPayment extends Model
{
    use HasFactory;

    public function getModulus()
    {
        return $this->hasOne(Modulus::class, 'id', 'modulus_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function module()
    {
        return $this->belongsTo(Modulus::class, 'modulus_id', 'id');
    }

}
