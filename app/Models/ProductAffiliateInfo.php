<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAffiliateInfo extends Model
{
    use HasFactory;

    public function commissions()
    {
        return $this->hasMany(ProductAffiliateCommission::class, 'affiliate_user_id', 'user_id');
    }
}
