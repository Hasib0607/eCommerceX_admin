<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickLogin extends Model
{
    use HasFactory;
    protected $guarded = []; 
    
    public function storeInfo()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }
}
