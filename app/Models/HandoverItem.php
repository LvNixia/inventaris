<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverItem extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'specifications' => 'array',
    ];

    public function handoverDocument(): BelongsTo
    {
        return $this->belongsTo(HandoverDocument::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function userEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_employee_id');
    }
}
