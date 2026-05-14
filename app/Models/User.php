<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'google_id',
        'social_img',
        'store_id',
        'auth_type',
        'otp',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class, 'uid', 'id');
    }

    public static function getStaff($staff_id = null)
    {
        return Superstaff::where('id', $staff_id)->first()->uid ?? null;
    }

    public function customerInfo()
    {
        return $this->hasOne(SuperstaffSalesCommission::class, 'user_id', 'id');
    }

    public function addonOrder()
    {
        return $this->hasMany(AddonsOrder::class, 'user_id', 'id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function storeInfo()
    {
        return $this->hasOne(Store::class, 'user_id', 'id');
    }

    public function getStore()
    {
        return $this->hasOne(Store::class, 'user_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    /**
     * Determine if the user is a super admin.
     *
     * @return bool
     */
    public function isSuperAdmin()
    {
        return $this->type === 'superadmin';
    }

    public function affiliate_info()
    {
        return $this->hasOne(ProductAffiliateInfo::class);
    }

    public static function storeCount($id = '')
    {
        $store = Store::where("user_id", $id)->get();
        if (isset($store) && $store->count() > 0) {
            return $store->count();
        } else {
            return 0;
        }
    }

}
