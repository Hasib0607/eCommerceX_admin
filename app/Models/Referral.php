<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;
    protected $guarded = [];


    public function PlanName(){
        return $this->hasOne(Plan::class, 'id', 'plan_id');
    }

    public function DigiPlanName(){
        return $this->hasOne(Digitalplan::class, 'id', 'plan_id');
    }
    public function PosPlanName(){
        return $this->hasOne(Posplan::class, 'id', 'plan_id');
    }

    public function getUser(){
        return $this->hasOne(User::class,'id', 'user_id');
    }

    public function getStore(){
        return $this->hasOne(Store::class,'id', 'store_id');
    }

}
