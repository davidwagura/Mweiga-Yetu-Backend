<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'unique_id',
        'user_id',
        'description',
        'start_dateTime',
        'end_dateTime',
        'category_id',
        'urgent',
        'attending_count',
        'location',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'event_attendees');
    }

}
