<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateWithdraw extends Model
{
    use HasFactory;

    protected $table = 'affiliate_withdraw_transactions';

    protected $guarded = [];

}
