<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'id', 'customer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function headerSetting()
    {
        // Multiple header rows per store can exist; website settings always updates the latest row.
        return $this->hasOne(Headersetting::class, 'store_id', 'id')->latestOfMany('id');
    }

    public function getUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function getPlan()
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function digitalplan()
    {
        return $this->hasOne(Digitalplan::class, 'id', 'digital_plan_id');
    }

    public function content()
    {
        return $this->hasMany(Content::class, 'store_id', 'id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'store_id');
    }

    public function current_currency()
    {
        return $this->belongsTo(Currency::class, 'currency', 'id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'store_id', 'id');
    }

    public function getExpiryDateAttribute($value)
    {
        if ((int) $this->store_status === 0) {
            return now()->subDay();
        }

        return $value;
    }

    public function getBalanceAttribute()
    {
        return abs($this->accountJournals()
            ->selectRaw('SUM(dr) - SUM(cr) as balance')
            ->value('balance') ?? 0);
    }

    public function accountJournals()
    {
        return $this->hasMany(AccountJournal::class, 'store_id');
    }

    public function activityComments()
    {
        return $this->hasMany(ClientActivitieComments::class, 'store_id', 'id');
    }

    public function addonsOrders()
    {
        return $this->hasMany(AddonsOrder::class, 'store_id', 'id');
    }

    public function userAnalytics()
    {
        return $this->hasMany(AdminUserAnalytics::class, 'store_id', 'id');
    }
}