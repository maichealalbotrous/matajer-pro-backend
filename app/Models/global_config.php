<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class global_config extends Model
{
    protected $fillable = [
        'config_key',
        'config_value',
        'last_updated_by',
    ];
}