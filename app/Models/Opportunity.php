<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'deadline',
        'category_id',
        'organization',
        'application_link',
        'unique_id',
        'user_id',
    ];

    protected $casts = [
        'deadline' => 'date'
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
