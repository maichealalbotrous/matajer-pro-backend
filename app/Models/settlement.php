<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class settlement extends Model
{
    protected $fillable = ['branch_id', 'invoice_id', 'gross_profit', 'net_profit', 'approved_by', 'settlement_date'];


    public function branch()
    {
        return $this->belongsTo(branche::class, 'branch_id');
    }

    public function invoice()
    {
        return $this->belongsTo(invoice::class, 'invoice_id');
    }


    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}