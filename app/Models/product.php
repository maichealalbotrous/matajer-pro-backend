<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class product extends Model
{
    protected $fillable = [
        'model_number',
        'model_name',
        'image',
        'branch_id',
    ];

    protected function branche()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }

    protected function variants()
    {
        return $this->hasMany(product_variant::class, 'product_id');
    }
}