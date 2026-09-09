<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Syarat pembayaran vendor. `days` menentukan jarak jatuh tempo dari tanggal
 * faktur; nol berarti bayar di tempat atau di muka.
 */
class PaymentTerm extends Model
{
    protected $guarded = [];

    protected $casts = [
        'days' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Jatuh tempo untuk sebuah tanggal faktur.
     */
    public function dueDateFrom(Carbon|string $invoiceDate): Carbon
    {
        return Carbon::parse($invoiceDate)->copy()->addDays($this->days);
    }
}
