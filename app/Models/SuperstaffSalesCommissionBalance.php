<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SuperstaffSalesCommissionBalance extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(SuperstaffSalesCommissionBalance::class, 'commission_id', 'id');
    }

    public static function getSellerCommissionBalance($staff_id)
    {
        // Use the query builder to calculate the balance amount
        $balance = \Illuminate\Support\Facades\DB::table('superstaff_sales_commission_balances')
            ->select(DB::raw('SUM(dr) - SUM(cr) as balance_amount'))
            ->where('staff_id', $staff_id)
            ->value('balance_amount');

        // If the balance is null, set it to 0
        return abs($balance) ?? 0;
    }


}
