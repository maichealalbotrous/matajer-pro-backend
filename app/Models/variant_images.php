<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class variant_images extends Model
{
    protected $fillable = [
        'variant_id',
        'image_path',
    ];

    protected function productVariant()
    {
        return $this->belongsTo(product_variant::class, 'variant_id');
    }
}