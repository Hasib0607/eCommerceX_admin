<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Modulus extends Model
{
    use HasFactory;

    protected $table = 'moduluses';

    public function getModulus(){
        return $this->hasOne(BuyModulus::class,'modulus_id','id');
    }

    public function getModulusPayment(){
        return $this->hasOne(ModulusPayment::class,'modulus_id','id');
    }
}
