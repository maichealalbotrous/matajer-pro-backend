<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class expense extends Model
{
    protected $fillable = [
        'branch_id',
        'type',
        'amount',
        'notes',
    ];

    public function branch()
    {
        return $this->belongsTo(branche::class);
    }
}