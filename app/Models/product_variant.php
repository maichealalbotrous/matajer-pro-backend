<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class product_variant extends Model
{
    protected $fillable = [
        'product_id',
        'size',
        'cost_price',
        'variant_name',
        'sku',
        'color',
    ];

    protected function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }   

    protected function inventories()
    {
        return $this->hasMany(inventory::class, 'variant_id');
    }

    protected function variantImages()
    {
        return $this->hasMany(variant_images::class, 'variant_id');
    }
}