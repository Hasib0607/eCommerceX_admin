<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RenewalCampaignBatchDispatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function batch()
    {
        return $this->belongsTo(RenewalCampaignBatch::class, 'batch_id');
    }

    public function item()
    {
        return $this->belongsTo(RenewalCampaignBatchItem::class, 'batch_item_id');
    }
}
