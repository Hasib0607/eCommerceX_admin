<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminUserAnalytics extends Model
{
    use HasFactory;

    /**
     * Get user data
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    /**
     * Get store data
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function getStore()
    {
        return $this->hasOne(Store::class, 'id', 'store_id');
    }
    public function getAdminAnalytics()
    {
        return $this->hasMany(AdminUserAnalytics::class, 'store_id', 'store_id');
    }

    /**
     * Get client followup activity data
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function followUp()
    {
        return $this->belongsTo(ClientActivitieComments::class, 'store_id', 'store_id')->orderBy('updated_at', 'DESC');
    }

}
