<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetService as AssetServiceModel;
use App\Models\PurchaseInvoice;

/**
 * Menelusuri kejanggalan yang tidak bisa dicegah oleh validasi formulir.
 *
 * Pemeriksaan lama menghitung keseimbangan kolom qty_*. Kolom itu tidak ada
 * lagi: satu baris berarti satu unit, jadi stok tidak mungkin timpang. Yang
 * perlu diawasi sekarang adalah keadaan unit yang saling bertentangan dan data
 * wajib yang belum terisi.
 */
class DataCheckService
{
    /**
     * @return array<int, array{type: string, id: int, reference: string, issue: string}>
     */
    public function runChecks(): array
    {
        return array_merge(
            $this->unitTanpaSerial(),
            $this->unitDipegangTapiStatusTidakDipakai(),
            $this->unitDilepasTapiMasihDipegang(),
            $this->unitTanpaStatus(),
            $this->servisBerjalanPadaUnitDilepas(),
            $this->unitTanpaBatchPembelian(),
            $this->fakturDenganTerbayarTidakCocok(),
            $this->fakturLunasTapiKurangBayar(),
        );
    }

    /**
     * `paid_amount` disimpan agar daftar hutang tidak menjumlah ulang tiap
     * tampil. Konsekuensinya nilai itu bisa menyimpang bila ada penulisan
     * langsung ke basis data di luar alur aplikasi, jadi diawasi di sini.
     */
    protected function fakturDenganTerbayarTidakCocok(): array
    {
        return PurchaseInvoice::query()
            ->with('vendor')
            ->get()
            ->filter(function (PurchaseInvoice $invoice): bool {
                $alokasi = (float) $invoice->allocations()
                    ->whereHas('vendorPayment', fn ($q) => $q->withoutGlobalScopes()->whereNull('cancelled_at'))
                    ->sum('amount');

                return abs($alokasi - (float) $invoice->paid_amount) > 0.5;
            })
            ->map(fn (PurchaseInvoice $invoice): array => [
                'type' => 'Faktur',
                'id' => $invoice->id,
                'reference' => $invoice->invoice_number.' · '.$invoice->vendor?->name,
                'issue' => 'Nilai terbayar pada faktur tidak sama dengan jumlah alokasi pembayarannya.',
            ])
            ->values()
            ->all();
    }

    protected function fakturLunasTapiKurangBayar(): array
    {
        return PurchaseInvoice::query()
            ->with('vendor')
            ->where('status', 'paid')
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->get()
            ->map(fn (PurchaseInvoice $invoice): array => [
                'type' => 'Faktur',
                'id' => $invoice->id,
                'reference' => $invoice->invoice_number.' · '.$invoice->vendor?->name,
                'issue' => 'Berstatus lunas padahal nilai terbayarnya kurang dari total tagihan.',
            ])
            ->values()
            ->all();
    }

    /**
     * Wajib bernomor seri tetapi belum diisi. Belum tentu salah — serial memang
     * boleh menyusul — tetapi unit ini akan tertahan saat hendak diserahkan.
     */
    protected function unitTanpaSerial(): array
    {
        return Asset::query()
            ->active()
            ->with('product.category')
            ->whereNull('serial_number')
            ->whereHas('product.category', fn ($category) => $category->where('requires_serial', true))
            ->get()
            ->map(fn (Asset $asset): array => [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => "Nomor seri belum diisi, padahal kategori {$asset->product?->category?->name} mewajibkannya.",
            ])
            ->all();
    }

    protected function unitDipegangTapiStatusTidakDipakai(): array
    {
        return Asset::query()
            ->held()
            ->with(['currentStatus', 'currentHolder'])
            ->whereHas('currentStatus', fn ($status) => $status->whereIn('code', ['spare', 'registered']))
            ->get()
            ->map(fn (Asset $asset): array => [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => "Tercatat dipegang {$asset->currentHolder?->name} tetapi statusnya {$asset->currentStatus?->name}.",
            ])
            ->all();
    }

    protected function unitDilepasTapiMasihDipegang(): array
    {
        return Asset::query()
            ->retired()
            ->with('currentHolder')
            ->whereNotNull('current_holder_id')
            ->get()
            ->map(fn (Asset $asset): array => [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => "Sudah dilepas tetapi masih tercatat dipegang {$asset->currentHolder?->name}.",
            ])
            ->all();
    }

    protected function unitTanpaStatus(): array
    {
        return Asset::query()
            ->whereNull('current_status_id')
            ->get()
            ->map(fn (Asset $asset): array => [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => 'Unit belum punya status; buka asetnya lalu tetapkan statusnya.',
            ])
            ->all();
    }

    protected function servisBerjalanPadaUnitDilepas(): array
    {
        return AssetServiceModel::query()
            ->where('status', 'open')
            ->whereHas('asset', fn ($asset) => $asset->withoutGlobalScopes()->whereNotNull('retired_at'))
            ->with('asset')
            ->get()
            ->map(fn (AssetServiceModel $service): array => [
                'type' => 'Servis',
                'id' => $service->id,
                'reference' => "#{$service->id} · {$service->asset?->asset_code}",
                'issue' => 'Catatan servis masih terbuka padahal unitnya sudah dilepas.',
            ])
            ->all();
    }

    /**
     * Tanpa batch, unit ini tidak punya harga dan tanggal beli, sehingga hilang
     * dari perhitungan nilai aset.
     */
    protected function unitTanpaBatchPembelian(): array
    {
        return Asset::query()
            ->active()
            ->whereNull('purchase_batch_id')
            ->get()
            ->map(fn (Asset $asset): array => [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => 'Belum tertaut ke batch pembelian, jadi tidak ikut terhitung pada nilai aset.',
            ])
            ->all();
    }
}
