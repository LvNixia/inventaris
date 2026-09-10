<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use App\Models\PurchaseOrder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    /**
     * Penerimaan bisa dibuka langsung dari daftar pesanan.
     *
     * Kalau `purchase_order_id` ikut di alamatnya, pesanannya langsung terpilih
     * dan sisa unitnya sudah tertarik menjadi baris — pengguna tinggal mengisi
     * nomor seri.
     */
    protected function afterFill(): void
    {
        $po = PurchaseOrder::find(request()->integer('purchase_order_id'));

        if (! $po) {
            return;
        }

        $this->data['purchase_order_id'] = $po->id;
        $this->data['branch_id'] = $po->branch_id;
        $this->data['vendor_id'] = $po->vendor_id;

        $baris = GoodsReceiptForm::barisSisaPesanan($po->id);

        if ($baris === []) {
            Notification::make()
                ->warning()
                ->title('Tidak ada sisa')
                ->body("Seluruh unit pada pesanan {$po->po_number} sudah diterima.")
                ->send();

            return;
        }

        $this->data['items'] = $baris;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Penerimaan selalu lahir sebagai draf; unit aset baru dibuat saat
        // penerimaannya disetujui dari halaman daftar.
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}
