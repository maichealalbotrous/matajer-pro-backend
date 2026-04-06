<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class payment_log extends Model
{
    protected $fillable = [
        'branch_id',
        'amount_paid',
        'currency',
        'payment_method',
        'reference_number',
    ];

    public function branch()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }
}