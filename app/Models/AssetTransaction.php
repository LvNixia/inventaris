<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTransaction extends Model
{
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function userEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_employee_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'status_id');
    }

    public function handoverDocument(): BelongsTo
    {
        return $this->belongsTo(HandoverDocument::class);
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function conditionAfter(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'condition_after_id');
    }

    public function disposalReason(): BelongsTo
    {
        return $this->belongsTo(DisposalReason::class, 'disposal_reason_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AssetService::class, 'service_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
