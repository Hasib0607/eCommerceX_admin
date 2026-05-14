<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreDesign extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'title_color', 'button', 'button_color', 'image_description', 'store_id', 'type'];
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
