<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    public function details()
    {
        return $this->hasMany(PlanDetail::class, 'plan_id', 'id')->orderBy('position', 'asc');
    }
}
