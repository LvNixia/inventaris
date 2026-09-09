<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penerimaan barang fisik. Di sinilah unit aset lahir.
 *
 * Selama masih draf, isinya bebas diubah dan belum ada aset yang dibuat.
 * Persetujuan yang mengubahnya menjadi unit nyata, lengkap dengan harga dan
 * garansi dari batch pembelian yang ikut dibuat.
 */
class GoodsReceipt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'receipt_date' => 'date',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function assets(): HasMany
    {
        return $this->hasManyThrough(
            Asset::class,
            GoodsReceiptItem::class,
            'goods_receipt_id',
            'id',
            'id',
            'asset_id',
        );
    }

    public function purchaseInvoices(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseInvoice::class, 'purchase_invoice_receipts')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /** Banyaknya unit pada penerimaan ini. */
    public function getQuantityAttribute(): int
    {
        return $this->items()->count();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
