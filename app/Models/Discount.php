<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'product_id',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'is_active',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(branche::class);
    }

    public function product()
    {
        return $this->belongsTo(product::class);
    }
}