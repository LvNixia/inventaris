<?php

namespace App\Filament\Support;

use App\Models\Asset;
use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dropdown pemilih unit aset yang seragam di seluruh form.
 *
 * Nilai yang disimpan tetap `asset_id`; yang berubah hanya label yang dibaca
 * user. Pencarian menjangkau merek, model, serial number, kode aset, dan
 * kategori sekaligus, sehingga user bisa mengetik "thinkpad" maupun "LAP-001".
 */
class AssetSelect
{
    /**
     * Jumlah opsi yang dimuat sekaligus. Membatasi agar dropdown tetap ringan
     * saat data aset sudah banyak; sisanya dijangkau lewat pencarian.
     */
    protected const LIMIT = 50;

    /**
     * @param  Closure|null  $modifyQueryUsing  Penyaring tambahan, misal hanya unit yang ada di gudang.
     * @param  Closure|null  $labelUsing  Pengganti label bawaan, menerima satu Asset.
     */
    public static function make(
        string $name = 'asset_id',
        ?Closure $modifyQueryUsing = null,
        ?Closure $labelUsing = null,
    ): Select {
        $label = $labelUsing ?? fn (Asset $asset): string => $asset->display_name;

        return Select::make($name)
            ->label('Aset')
            ->placeholder('Pilih Aset')
            ->searchable()
            ->options(fn (): array => static::query($modifyQueryUsing)
                ->limit(static::LIMIT)
                ->get()
                ->mapWithKeys(fn (Asset $asset): array => [$asset->id => $label($asset)])
                ->all())
            ->getSearchResultsUsing(fn (string $search): array => static::query($modifyQueryUsing)
                ->where(fn (Builder $query) => $query
                    ->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('product', fn (Builder $product) => $product
                        ->where('model', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))))
                ->limit(static::LIMIT)
                ->get()
                ->mapWithKeys(fn (Asset $asset): array => [$asset->id => $label($asset)])
                ->all())
            // Dipakai saat form memuat data lama: nilai tersimpan diterjemahkan
            // kembali menjadi label, walau aset itu di luar opsi yang dimuat.
            ->getOptionLabelUsing(function ($value) use ($label): ?string {
                $asset = Asset::with(['product.brand', 'product.category'])->find($value);

                return $asset ? $label($asset) : null;
            });
    }

    protected static function query(?Closure $modifyQueryUsing): Builder
    {
        $query = Asset::query()
            ->with(['product.brand', 'product.category'])
            ->orderBy('asset_code');

        if ($modifyQueryUsing) {
            $modifyQueryUsing($query);
        }

        return $query;
    }
}
