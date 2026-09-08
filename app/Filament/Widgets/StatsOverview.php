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
        // Scope to current branch for branch admins
        $query = Asset::query();

        // 1. Total Unit Aktif
        $totalActive = $query->sum(DB::raw('quantity - qty_writeoff'));
        
        // 2. Unit di Gudang
        $totalAvailable = $query->sum('qty_available');
        
        // 3. Unit Dipegang
        $totalHeld = $query->sum(DB::raw('qty_out - qty_in'));
        
        // 4. Nilai Aset Aktif
        $totalValue = $query->sum(DB::raw('(quantity - qty_writeoff) * unit_price'));

        // 5. Belum pernah diserahkan
        $neverHandedOver = (clone $query)->where('qty_out', 0)->count();

        // 6. Dilepas / Dijual (Writeoff)
        $totalWriteoff = $query->sum('qty_writeoff');

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

            Stat::make('Total Nilai Aset Aktif', 'Rp ' . number_format($totalValue, 0, ',', '.'))
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
