<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'uid');
    }

    public function activestore()
    {
        return $this->hasOne(Store::class, 'id', 'active_store');
    }

    public function getStore()
    {
        return $this->belongsTo(Store::class, 'active_store');
    }

    public function store()
    {
        return $this->hasMany(Store::class, 'customer_id', 'id');
    }
}
