<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'name',
        'number_of_units',
        'address',
        'user_id',
    ];

    public function residents()
    {
        return $this->hasMany(Resident::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
