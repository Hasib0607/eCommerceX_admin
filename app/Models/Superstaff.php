<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Superstaff extends Model
{
    use HasFactory;

    public function getWork()
    {
        return $this->hasMany(Websitesetup::class, 'editor', 'uid');
    }

    public function sales()
    {
        return $this->hasMany(SuperstaffSalesCommissionBalance::class, 'staff_id', 'id');
    }

    public function role()
    {
        return $this->belongsTo(Superrole::class, 'role_id', 'id');
    }


}
