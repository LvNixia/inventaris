<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Scopes\BranchScope;

class HandoverDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'document_date' => 'date',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'legacy' => 'boolean',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function firstParty(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'first_party_id');
    }

    public function secondParty(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'second_party_id');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'witness_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HandoverItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AssetTransaction::class);
    }
}
