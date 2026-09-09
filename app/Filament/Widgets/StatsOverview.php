<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan Aset';

    protected ?string $description = 'Angka mengikuti cakupan cabang pengguna yang sedang masuk.';

    /**
     * Enam kartu ditata 3 + 3 di layar lebar, menyusut mengikuti lebar layar.
     */
    protected function getColumns(): int|array|null
    {
        return ['@md' => 2, '@xl' => 3];
    }

    protected function getStats(): array
    {
        // Cakupan cabang mengikuti BranchScope pada model Asset.
        // Satu baris aset berarti satu unit, jadi semuanya dicacah, bukan dijumlahkan.
        $totalActive = Asset::query()->active()->count();

        $totalAvailable = Asset::query()->available()->count();

        $totalHeld = Asset::query()->held()->count();

        // Nilai diambil dari harga satuan batch pembeliannya.
        $totalValue = (float) Asset::query()
            ->active()
            ->join('purchase_batches', 'purchase_batches.id', '=', 'assets.purchase_batch_id')
            ->sum(DB::raw('COALESCE(purchase_batches.unit_price, 0)'));

        $neverHandedOver = Asset::query()
            ->active()
            ->whereDoesntHave('assetTransactions', fn ($tx) => $tx->where('stock_direction', 'out'))
            ->count();

        $totalWriteoff = Asset::query()->retired()->count();

        return [
            Stat::make('Total Unit Aktif', number_format($totalActive, 0, ',', '.'))
                ->description('Barang yang masih dimiliki (di luar dilepas/dijual)')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Unit di Gudang (Siap Pakai)', number_format($totalAvailable, 0, ',', '.'))
                ->description('Jumlah unit yang berada di gudang')
                ->descriptionIcon('heroicon-m-home')
                ->color('success'),

            Stat::make('Unit Dipegang', number_format($totalHeld, 0, ',', '.'))
                ->description('Jumlah unit yang sedang dibawa karyawan')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Total Nilai Aset Aktif', 'Rp '.number_format($totalValue, 0, ',', '.'))
                ->description('Estimasi nilai berdasarkan harga satuan x stok aktif')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Belum Pernah Diserahkan', number_format($neverHandedOver, 0, ',', '.'))
                ->description('Baris aset yang tidak memiliki riwayat keluar')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('warning'),

            Stat::make('Unit Dilepas / Dijual', number_format($totalWriteoff, 0, ',', '.'))
                ->description('Barang yang sudah dihapusbukukan (Write-off)')
                ->descriptionIcon('heroicon-m-archive-box-x-mark')
                ->color('danger'),
        ];
    }
}
