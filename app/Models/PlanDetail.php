<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanDetail extends Model
{
    use HasFactory;
    public function setStatusAttribute($value)
    {
        $this->attributes['status'] = $value === 'ON' ? true : false;
    }
}
