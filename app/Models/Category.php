<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type'
    ];

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
