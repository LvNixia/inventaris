<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu unit fisik yang diterima. Nomor serinya menginap di sini sampai
 * penerimaan disetujui, lalu unit asetnya dibuat dan ditautkan lewat `asset_id`.
 */
class GoodsReceiptItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'warranty_months' => 'integer',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Unit ini wajib bernomor seri tetapi belum diisi.
     */
    public function isMissingRequiredSerial(): bool
    {
        return $this->product?->requiresSerialNumber() && blank($this->serial_number);
    }
}
