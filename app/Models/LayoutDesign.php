<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LayoutDesign extends Model
{
    use HasFactory;

    protected $fillable = ['color', 'bg_color', 'hover_color', 'size'];
}
