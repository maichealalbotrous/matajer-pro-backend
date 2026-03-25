<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class product extends Model
{
    protected $fillable = [
        'model_number',
        'model_name',
        'image',
        'branch_id',
    ];

    protected static function booted()
{
    static::deleting(function ($product) {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        foreach ($product->variants as $variant) {
            $variant->delete(); 
        }
    });
}

    public function branche()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }

    public function variants()
    {
        return $this->hasMany(product_variant::class, 'product_id');
    }

    
}