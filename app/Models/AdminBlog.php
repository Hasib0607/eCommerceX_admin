<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminBlog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'sub_title',
        'key_word',
        'description',
        'thumbnail',
        'image',
        'position',
        'slug',
        'status'
    ];


    public function type()
    {
        return $this->belongsTo(AdminBlogType::class, 'type');
    }

}
