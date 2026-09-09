<?php

namespace App\Filament\Resources\PurchaseBatches\Schemas;

use App\Enums\Role;
use App\Models\Product;
use App\Models\PurchaseBatch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

/**
 * Satu layar untuk menerima satu pembelian.
 *
 * Data faktur diketik sekali; daftar unit di bawahnya menentukan berapa unit
 * yang lahir dari pembelian ini. Untuk barang bernomor seri, tiap baris diisi
 * serialnya. Untuk barang tanpa serial, cukup tambah baris sebanyak unitnya.
 */
class PurchaseBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Barang & Faktur')
                    ->description('Berlaku untuk seluruh unit dalam pembelian ini.')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Barang')
                            ->placeholder('Pilih Barang')
                            ->relationship('product', 'model')
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                            ->searchable(['model'])
                            ->preload()
                            ->required()
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Membeli ulang barang yang sudah ada? Pilih barang yang sama, jangan buat barang baru.'),

                        Select::make('vendor_id')
                            ->label('Vendor / Toko')
                            ->placeholder('Pilih Vendor')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('invoice_number')
                            ->label('Nomor Faktur')
                            ->maxLength(255),

                        DatePicker::make('purchase_date')
                            ->label('Tanggal Beli')
                            ->default(now())
                            ->maxDate(now()),

                        TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ',']),

                        TextInput::make('warranty_months')
                            ->label('Garansi (bulan)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Tanggal akhir garansi dihitung otomatis dari tanggal beli.'),

                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn () => auth()->user()->role !== Role::AdminPusat)
                            ->dehydrated(),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ]),

                Section::make('Unit dari Pembelian Ini')
                    ->description('Unit ditambahkan lewat Penerimaan Barang, bukan dari sini.')
                    ->schema([
                        TextInput::make('jumlah_unit')
                            ->label('Jumlah Unit')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?PurchaseBatch $record): int => $record?->assets()->count() ?? 0),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    protected static function produk(mixed $productId): ?Product
    {
        return blank($productId) ? null : Product::with('category')->find($productId);
    }

    protected static function wajibSerial(mixed $productId): bool
    {
        return (bool) static::produk($productId)?->requiresSerialNumber();
    }

    protected static function kategoriKode(mixed $productId): ?string
    {
        return static::produk($productId)?->category?->code_prefix;
    }
}
