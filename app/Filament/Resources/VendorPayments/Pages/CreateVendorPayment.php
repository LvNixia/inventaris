<?php

namespace App\Filament\Resources\VendorPayments\Pages;

use App\Filament\Resources\VendorPayments\VendorPaymentResource;
use App\Services\VendorPaymentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    /**
     * Nomor pembayaran, pemeriksaan alokasi, dan pembaruan status faktur
     * seluruhnya diurus service dalam satu transaksi.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $alokasi = $data['allocations'] ?? [];
        unset($data['allocations']);

        return app(VendorPaymentService::class)->pay($data, $alokasi);
    }
}
