<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unit fisik. Identitas barangnya ada di Product, data pembeliannya di
 * PurchaseBatch, dan baris ini hanya menyimpan keadaan unit itu sendiri:
 * nomor seri, kondisi, status, serta siapa yang memegangnya.
 */
class Asset extends Model
{
    protected $guarded = [];

    protected $casts = [
        // Diisi hanya bila unit menyimpang dari spesifikasi produknya.
        'specifications' => 'array',
        'retired_at' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseBatch(): BelongsTo
    {
        return $this->belongsTo(PurchaseBatch::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(Condition::class);
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_holder_id');
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_user_id');
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'current_status_id');
    }

    public function assetServices(): HasMany
    {
        return $this->hasMany(AssetService::class);
    }

    public function assetTransactions(): HasMany
    {
        return $this->hasMany(AssetTransaction::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }

    public function handoverItems(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }

    /*
     * Jalan pintas ke data produk dan pembelian, supaya kode dan tampilan yang
     * hanya butuh membaca tidak perlu menelusuri relasi setiap kali.
     */

    public function getCategoryAttribute(): ?Category
    {
        return $this->product?->category;
    }

    public function getBrandAttribute(): ?Brand
    {
        return $this->product?->brand;
    }

    public function getModelNameAttribute(): ?string
    {
        return $this->product?->model;
    }

    public function getVendorAttribute(): ?Vendor
    {
        return $this->purchaseBatch?->vendor;
    }

    public function getPurchaseDateAttribute()
    {
        return $this->purchaseBatch?->purchase_date;
    }

    public function getUnitPriceAttribute(): ?string
    {
        return $this->purchaseBatch?->unit_price;
    }

    public function getInvoiceNumberAttribute(): ?string
    {
        return $this->purchaseBatch?->invoice_number;
    }

    public function getWarrantyUntilAttribute()
    {
        return $this->purchaseBatch?->warranty_until;
    }

    /**
     * Spesifikasi yang berlaku: milik unit bila pernah diubah lewat upgrade,
     * kalau tidak ikut spesifikasi bawaan produknya.
     */
    public function getEffectiveSpecificationsAttribute(): ?array
    {
        return $this->specifications ?? $this->product?->specifications;
    }

    /**
     * Label identitas unit untuk dropdown, tabel, dan pencarian.
     * Contoh: "Lenovo ThinkPad T14 — S/N 5CD123 · IDS-LAP-001".
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->product?->name ?? 'Aset';

        if ($this->serial_number) {
            $name .= ' — S/N '.$this->serial_number;
        }

        return $name.' · '.$this->asset_code;
    }

    /**
     * Unit ini wajib punya nomor seri tetapi belum diisi. Dipakai untuk memagari
     * serah terima, kirim antar cabang, dan servis dengan barang ditinggal.
     */
    public function isMissingRequiredSerial(): bool
    {
        return $this->product?->requiresSerialNumber() && blank($this->serial_number);
    }

    /*
     * Penyaring keadaan unit. Menggantikan aritmetika kolom qty_* yang dulu
     * harus dijaga tetap sinkron: keadaan sekarang dibaca langsung dari unit.
     */

    /** Ada di gudang dan siap diserahkan. */
    public function scopeAvailable($query)
    {
        return $query->whereNull('current_holder_id')
            ->whereNull('retired_at')
            ->whereHas('currentStatus', fn ($status) => $status->where('transferable', true));
    }

    /** Sedang dipegang karyawan. */
    public function scopeHeld($query)
    {
        return $query->whereNotNull('current_holder_id')->whereNull('retired_at');
    }

    /** Masih tercatat sebagai milik perusahaan. */
    public function scopeActive($query)
    {
        return $query->whereNull('retired_at');
    }

    /** Sudah dihapusbukukan. */
    public function scopeRetired($query)
    {
        return $query->whereNotNull('retired_at');
    }

    public function isAvailable(): bool
    {
        return $this->current_holder_id === null
            && $this->retired_at === null
            && (bool) $this->currentStatus?->transferable;
    }

    public function isHeld(): bool
    {
        return $this->current_holder_id !== null && $this->retired_at === null;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }
}
