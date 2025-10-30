<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'title',
        'description',
        'images',
        'progress_percentage',
        'timeline',
        'budget',
        'beneficiaries',
        'location',
        'start_date',
        'status_id',
        'unique_id',
        'user_id',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
