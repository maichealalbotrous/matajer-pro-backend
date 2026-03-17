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

    protected function branche()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }

    protected function productVariant()
    {
        return $this->belongsTo(product_variant::class, 'variant_id');
    }
}