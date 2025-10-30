<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'unique_id',
        'user_id',
        'description',
        'category_id',
        'date',
        'images'
    ];

    protected $casts = [
        'images' => 'array',
        'date' => 'date'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
