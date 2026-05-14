<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddonsOrderPaymentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'addons_order_id',
        'payment_amount',
        'previous_paid_amount',
        'previous_due_amount',
        'current_paid_amount',
        'current_due_amount',
        'due_amount_status',
        'payment_method',
        'payment_number',
        'transaction_id',
        'bank_name',
        'account_number',
        'note',
        'created_by',
    ];

    protected $casts = [
        'payment_amount' => 'float',
        'previous_paid_amount' => 'float',
        'previous_due_amount' => 'float',
        'current_paid_amount' => 'float',
        'current_due_amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(AddonsOrder::class, 'addons_order_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
