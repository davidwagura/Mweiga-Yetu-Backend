<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
        'image_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'image_path' => 'string',
    ];

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }

    public function attendingEvents()
    {
        return $this->belongsToMany(Event::class, 'event_attendees');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

}
