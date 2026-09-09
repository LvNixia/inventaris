<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetService extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'expected_at' => 'datetime',
        'finished_at' => 'datetime',
        'service_warranty_until' => 'date',
        'spec_before' => 'array',
        'spec_after' => 'array',
        'item_left' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function serviceKind(): BelongsTo
    {
        return $this->belongsTo(ServiceKind::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function serviceResult(): BelongsTo
    {
        return $this->belongsTo(ServiceResult::class);
    }

    public function conditionAfter(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'condition_after_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class, 'service_id');
    }
}
