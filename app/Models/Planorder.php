<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planorder extends Model
{
    use HasFactory;

    public function webPlan(){
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    public function posPlan(){
        return $this->hasOne(Posplan::class, 'id', 'pos_plan_id');
    }

    public function smmPlan(){
        return $this->hasOne(Digitalplan::class, 'id', 'digital_plan_id');
    }
}
