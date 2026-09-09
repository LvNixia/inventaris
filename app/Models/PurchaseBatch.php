<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris faktur pembelian. Data harga, tanggal, vendor, dan garansi
 * berlaku untuk seluruh unit yang lahir dari batch ini.
 */
class PurchaseBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        'unit_price' => 'decimal:2',
        'warranty_months' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Banyaknya unit yang lahir dari batch ini.
     */
    public function getQuantityAttribute(): int
    {
        return $this->assets()->count();
    }
}
