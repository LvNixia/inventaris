<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetAttachment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AssetService::class, 'service_id');
    }

    public function attachmentType(): BelongsTo
    {
        return $this->belongsTo(AttachmentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Hapus berkas fisik saat baris lampiran dihapus agar disk tidak menumpuk sampah.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $attachment): void {
            $disk = Storage::disk(config('filesystems.default'));

            if ($attachment->path && $disk->exists($attachment->path)) {
                $disk->delete($attachment->path);
            }
        });
    }
}
