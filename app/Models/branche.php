<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class branche extends Model
{
    protected $fillable = [
        'name',
        'location',
        'subscription_code',
        'expiry_date',
        'is_active',
    ];

    protected function subscriptionKey()
    {
        return $this->hasOne(subscription_keys::class, 'branch_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    protected function products()
    {
        return $this->hasMany(Product::class, 'branch_id');
    }

    protected function inventories()
    {
        return $this->hasMany(inventory::class, 'branch_id');
    }

    public function invoices()
    {
        return $this->hasMany(invoice::class, 'branch_id');
    }

    public function settlements()
    {
        return $this->hasMany(settlement::class, 'branch_id');
    }

    public function discounts()
    {
        return $this->hasMany(Discount::class, 'branch_id');
    }

    public function expenses()
    {
        return $this->hasMany(expense::class, 'branch_id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(payment_log::class, 'branch_id');
    }
}