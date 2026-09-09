<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pesanan pembelian ke vendor.
 *
 * Nomor PO baru terbit saat disetujui, mengikuti pola surat serah terima:
 * dokumen yang masih draf belum punya identitas resmi. Nilai subtotal, pajak,
 * dan total disimpan agar PO lama tidak ikut berubah saat harga barang
 * diperbarui belakangan.
 */
class PurchaseOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'po_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Status yang isinya masih boleh diubah.
     */
    public const DAPAT_DIUBAH = ['draft'];

    /**
     * Status yang berarti PO sudah selesai atau tidak berlaku.
     */
    public const SELESAI = ['completed', 'cancelled'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::DAPAT_DIUBAH, true);
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partial_receipt', 'completed'], true);
    }

    /** Jumlah unit yang dipesan pada seluruh baris. */
    public function getOrderedQuantityAttribute(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
