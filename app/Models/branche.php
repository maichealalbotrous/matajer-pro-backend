<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class branche extends Model
{
    protected $fillable = [
        'name',
        'location',
        'subscription_code',
        'expiry_date',
        'is_active',
    ];

    protected function subscriptionKey()
    {
        return $this->hasOne(subscription_keys::class, 'branch_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}