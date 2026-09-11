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
     * Penerimaan yang layak dimasukkan ke sebuah faktur.
     *
     * Selain belum ditagih faktur lain, penerimaannya juga harus belum lunas:
     * yang sudah dibayar di muka tidak punya tagihan untuk dicatat.
     */
    public function scopeBisaDitagih($query, ?int $kecualiInvoiceId = null)
    {
        return $query->belumDitagih($kecualiInvoiceId)->whereNull('purchase_reference');
    }

    /**
     * Bandingkan harga yang benar-benar dibayar dengan yang disetujui di
     * pesanan, hanya untuk unit yang ada di penerimaan ini.
     *
     * Perbandingannya per unit, bukan terhadap total pesanan, supaya
     * penerimaan sebagian tidak selalu terlihat lebih murah. Selisih harga itu
     * wajar — yang tidak wajar adalah selisih yang tidak diketahui siapa pun,
     * jadi hasilnya dipakai untuk memberi tahu, bukan menolak.
     *
     * @return array{pesanan: float, penerimaan: float, selisih: float, persen: float}|null
     *                                                                                      Null bila penerimaan ini tidak berasal dari pesanan.
     */
    public function selisihHarga(): ?array
    {
        $this->loadMissing('items.purchaseOrderItem');

        $terpesan = $this->items->filter(fn (GoodsReceiptItem $item): bool => $item->purchaseOrderItem !== null);

        if ($terpesan->isEmpty()) {
            return null;
        }

        $pesanan = (float) $terpesan->sum(fn (GoodsReceiptItem $item): float => (float) $item->purchaseOrderItem->unit_price);
        $penerimaan = (float) $terpesan->sum(
            fn (GoodsReceiptItem $item): float => (float) ($item->unit_price ?? $item->purchaseOrderItem->unit_price)
        );

        $selisih = $penerimaan - $pesanan;

        return [
            'pesanan' => $pesanan,
            'penerimaan' => $penerimaan,
            'selisih' => $selisih,
            'persen' => $pesanan > 0 ? $selisih / $pesanan * 100 : 0.0,
        ];
    }

    /**
     * Ringkasan selisih harga dalam kalimat, atau null bila masih dalam batas
     * wajar. Ambangnya 10 persen: pergerakan harga di bawah itu terlalu sering
     * terjadi untuk pantas diperingatkan.
     */
    public function peringatanSelisihHarga(float $ambangPersen = 10): ?string
    {
        $selisih = $this->selisihHarga();

        if (! $selisih || abs($selisih['persen']) < $ambangPersen) {
            return null;
        }

        return sprintf(
            'Harga %s dari pesanan: Rp %s menjadi Rp %s (%s%s%%).',
            $selisih['selisih'] > 0 ? 'naik' : 'turun',
            number_format($selisih['pesanan'], 0, ',', '.'),
            number_format($selisih['penerimaan'], 0, ',', '.'),
            $selisih['selisih'] > 0 ? '+' : '',
            number_format($selisih['persen'], 1, ',', '.'),
        );
    }

    /**
     * Penerimaan ini lunas di muka?
     *
     * Nomor nota terisi berarti barangnya sudah dibayar saat dipesan — belanja
     * marketplace atau beli langsung di toko. Penerimaan seperti itu tidak
     * boleh melahirkan tagihan, karena uangnya sudah keluar.
     */
    public function lunasDiMuka(): bool
    {
        return filled($this->purchase_reference);
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
