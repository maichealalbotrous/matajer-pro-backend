<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class subscription_keys extends Model
{
    protected $fillable = [
        'license_key',
        'duration_months',
        'activated_at',
        'deactivated_at',
        'is_used',
        'branch_id',        ];

        
        protected function branche()
        {
           return $this->hasOne(branche::class,'id');
        } 
}