<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddonsExpired extends Model
{
    use HasFactory;


    public function addonsName(){
        return $this->hasOne(AddonsApi::class, 'id', 'addons_id');
    }
}
