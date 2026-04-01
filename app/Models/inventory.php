<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class inventory extends Model
{
    protected $fillable = [
        'branch_id',
        'variant_id',
        'quantity',
        
    ];

    public function branch()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }

    public function variant()
    {
        return $this->belongsTo(product_variant::class, 'variant_id');
    }
}