<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalCampaignBatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(RenewalCampaignBatchItem::class, 'batch_id');
    }

    public function dispatches()
    {
        return $this->hasMany(RenewalCampaignBatchDispatch::class, 'batch_id');
    }
}
