<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AddonsOrder extends Model
{
    use HasFactory;


    /**
     * Write code on Method
     *
     * @return response()
     */
    protected $fillable = [
        'id',
        'user_id',
        'store_id',
        'addons',
        'payment_method',
        'payment_type',
        'payment_number',
        'transaction_id',
        'combopackages',
        'plan_id',
        'plan_type',
        'plan_month',
        'total',
        'status',
        'manual_discount',
        'manual_discount_comment',
        'paid_amount',
        'due_amount',
        'due_amount_status',
        'bank_name',
        'account_number',
    ];

    protected $casts = [
        'total' => 'float',
        'manual_discount' => 'float',
        'paid_amount' => 'float',
        'due_amount' => 'float',
    ];


    public function PlanName()
    {
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function paymentHistories()
    {
        return $this->hasMany(AddonsOrderPaymentHistory::class, 'addons_order_id', 'id')
            ->orderBy('created_at', 'desc');
    }


    /**
     * Get the user's first name.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function addons(): Attribute
    {
        return Attribute::make(
            get: fn($value) => json_decode($value, true),
            set: fn($value) => json_encode($value)
        );
    }

    /**
     * Get the user's first name.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function combopackages(): Attribute
    {
        return Attribute::make(
            get: fn($value) => json_decode($value, true),
            set: fn($value) => json_encode($value)
        );
    }
}
