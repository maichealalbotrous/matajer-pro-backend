<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class invoice extends Model
{
    protected $fillable = ['branch_id', 'user_id', 'total_amount', 'status', 'type','notes','invoice_date'];

    public function invoiceItems()
    {
        return $this->hasMany(invoice_item::class, 'invoice_id');
    }

    public function branch()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function settlement()
    {
        return $this->hasOne(settlement::class, 'invoice_id');
    }
}