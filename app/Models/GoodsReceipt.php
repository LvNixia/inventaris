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

    /**
     * Nilai penerimaan ini, dijumlahkan dari harga tiap unitnya.
     *
     * Harga baris dipakai lebih dulu; kalau kosong, harga baris pesanannya yang
     * dipakai. Dipakai bersama oleh formulir faktur dan PurchaseInvoiceService,
     * supaya angka yang ditampilkan sama dengan angka yang tersimpan.
     */
    public function getTotalValueAttribute(): float
    {
        $this->loadMissing('items.purchaseOrderItem');

        return (float) $this->items->sum(
            fn (GoodsReceiptItem $item): float => (float) ($item->unit_price
                ?? $item->purchaseOrderItem?->unit_price
                ?? 0)
        );
    }

    /**
     * PPN penerimaan ini, dihitung dari persentase pajak baris pesanannya.
     *
     * Penerimaan tanpa pesanan tidak punya persentase pajak, jadi nilainya nol
     * dan PPN-nya diisi manual di faktur.
     */
    public function getTaxValueAttribute(): float
    {
        $this->loadMissing('items.purchaseOrderItem');

        return (float) $this->items->sum(function (GoodsReceiptItem $item): float {
            $harga = (float) ($item->unit_price ?? $item->purchaseOrderItem?->unit_price ?? 0);
            $persen = (float) ($item->purchaseOrderItem?->tax_percent ?? 0);

            return $harga * $persen / 100;
        });
    }

    /**
     * Penerimaan yang belum tercakup faktur mana pun yang masih berlaku.
     *
     * Faktur yang dibatalkan tidak dihitung, jadi penerimaannya kembali bisa
     * ditagihkan lewat faktur baru.
     */
    public function scopeBelumDitagih($query, ?int $kecualiInvoiceId = null)
    {
        return $query->whereDoesntHave(
            'purchaseInvoices',
            fn ($invoice) => $invoice->withoutGlobalScopes()
                ->where('purchase_invoices.status', '!=', 'cancelled')
                ->when($kecualiInvoiceId, fn ($q) => $q->where('purchase_invoices.id', '!=', $kecualiInvoiceId)),
        );
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
