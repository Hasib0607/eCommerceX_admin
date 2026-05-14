<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'amount',
        'date',
        'category',
        'notes',
        'user_id'
    ];


    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category', 'id');
    }


}
