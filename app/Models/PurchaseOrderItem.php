<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris pesanan. Jumlah yang sudah diterima tidak disimpan di sini;
 * dihitung dari baris penerimaan yang menunjuk ke baris ini, supaya tidak ada
 * angka yang harus dijaga tetap cocok.
 */
class PurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'total_price' => 'decimal:2',
        'warranty_months' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * Jumlah unit yang sudah benar-benar diterima untuk baris pesanan ini.
     */
    public function getReceivedQuantityAttribute(): int
    {
        return $this->goodsReceiptItems()
            ->whereHas('goodsReceipt', fn ($q) => $q->withoutGlobalScopes()->where('status', 'received'))
            ->count();
    }

    /**
     * Nilai baris sebelum pajak.
     */
    public function getSubtotalAttribute(): float
    {
        return (float) $this->unit_price * (int) $this->quantity;
    }

    public function getTaxAmountAttribute(): float
    {
        return $this->subtotal * ((float) $this->tax_percent / 100);
    }
}
