<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class invoice_item extends Model
{
    protected $fillable = ['invoice_id', 'variant_id', 'sold_at_price', 'quantity','item_notes'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function variant()
    {
        return $this->belongsTo(product_variant::class, 'variant_id');
    }
}