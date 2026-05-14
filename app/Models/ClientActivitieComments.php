<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientActivitieComments extends Model
{
    use HasFactory, SoftDeletes;

    /**
     *
     * Get User data
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    /**
     *
     * Get Store data
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getStore()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }

}
