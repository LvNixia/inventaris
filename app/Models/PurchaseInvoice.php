<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan resmi dari vendor.
 *
 * Nomornya berasal dari vendor, bukan dibuat sistem, jadi keunikannya
 * dipasangkan dengan vendor. Statusnya tidak dipilih manusia melainkan hasil
 * perbandingan `paid_amount` dengan `total_amount`.
 */
class PurchaseInvoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceReceipt::class);
    }

    public function goodsReceipts(): BelongsToMany
    {
        return $this->belongsToMany(GoodsReceipt::class, 'purchase_invoice_receipts')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Sisa yang belum dibayar. */
    public function getOutstandingAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->paid_amount;
    }

    /** Umur hutang dalam hari; negatif berarti belum jatuh tempo. */
    public function getAgeInDaysAttribute(): int
    {
        return (int) $this->due_date->diffInDays(now(), false);
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid'
            && $this->status !== 'cancelled'
            && $this->due_date->isPast();
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['unpaid', 'partial']);
    }

    public function scopeOverdue($query)
    {
        return $query->outstanding()->whereDate('due_date', '<', now());
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
