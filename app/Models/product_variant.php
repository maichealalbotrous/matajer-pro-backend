<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected static function booted()
{
    static::deleting(function ($variant) {
        foreach ($variant->variantImages as $image) {
            Storage::disk('public')->delete($image->image_path);
        }
    });
}

    public function product()
    {
        return $this->belongsTo(product::class, 'product_id');
    }   

    public function inventories()
    {
        return $this->hasMany(inventory::class, 'variant_id');
    }

    public function variantImages()
    {
        return $this->hasMany(variant_images::class, 'variant_id');
    }
}