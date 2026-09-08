<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_until' => 'date',
        // Kolom JSON: dipakai KeyValue pada form aset dan perlu berupa array.
        'specifications' => 'array',
        'accessories' => 'array',
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'qty_out' => 'integer',
        'qty_in' => 'integer',
        'qty_writeoff' => 'integer',
        'qty_available' => 'integer',
    ];

    public function handoverItems() { return $this->hasMany(HandoverItem::class); }

    public function branch() { return $this->belongsTo(Branch::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function condition() { return $this->belongsTo(Condition::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function currentHolder() { return $this->belongsTo(Employee::class, 'current_holder_id'); }
    public function currentUser() { return $this->belongsTo(Employee::class, 'current_user_id'); }
    public function currentStatus() { return $this->belongsTo(AssetStatus::class, 'current_status_id'); }
    public function splitFromAsset() { return $this->belongsTo(Asset::class, 'split_from_asset_id'); }
    public function assetServices() { return $this->hasMany(AssetService::class); }

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\BranchScope);
    }
}
