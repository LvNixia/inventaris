<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Katalog barang: satu baris per jenis barang, terlepas dari berapa kali
 * dibeli dan berapa unit yang dimiliki.
 */
class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'specifications' => 'array',
        'accessories' => 'array',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Nama barang untuk ditampilkan. Produk tidak punya kolom nama; identitasnya
     * gabungan merek dan model, dengan nama kategori sebagai cadangan.
     */
    public function getNameAttribute(): string
    {
        $name = collect([$this->brand?->name, $this->model])->filter()->implode(' ');

        return $name !== '' ? $name : ($this->category?->name ?? 'Barang');
    }

    /**
     * Apakah tiap unit produk ini wajib punya nomor seri.
     */
    public function requiresSerialNumber(): bool
    {
        return (bool) $this->category?->requires_serial;
    }

    /**
     * Sebutan penanda unik untuk kategori ini.
     *
     * Kolom yang dipakai sama, tetapi pada lisensi yang dicatat adalah kunci
     * produknya, bukan nomor seri perangkat. Menyebutnya apa adanya membuat
     * formulir dan pesan kesalahan tidak membingungkan.
     */
    public function serialLabel(): string
    {
        return $this->category?->code_prefix === 'LSS' ? 'Kunci Lisensi' : 'Nomor Seri';
    }
}
