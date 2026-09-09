<?php

namespace App\Filament\Resources\PurchaseBatches\Pages;

use App\Filament\Resources\PurchaseBatches\PurchaseBatchResource;
use App\Models\PurchaseBatch;
use App\Services\AssetService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Menyimpan pembelian sekaligus membuat unit-unitnya.
 *
 * Daftar unit pada formulir bukan relasi Eloquent, melainkan masukan untuk
 * AssetService::receivePurchase yang membuat batch dan unitnya dalam satu
 * transaksi basis data.
 */
class CreatePurchaseBatch extends CreateRecord
{
    protected static string $resource = PurchaseBatchResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $units = $data['units'] ?? [];
        unset($data['units']);

        $assets = app(AssetService::class)->receivePurchase($data, $units);

        Notification::make()
            ->success()
            ->title($assets->count().' unit dibuat')
            ->body('Kode unit: '.$assets->pluck('asset_code')->join(', '))
            ->send();

        return PurchaseBatch::findOrFail($assets->first()->purchase_batch_id);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
