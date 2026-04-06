<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class system_log extends Model
{
    protected $fillable = [
        'user_id',
        'action_type',
        'description',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}