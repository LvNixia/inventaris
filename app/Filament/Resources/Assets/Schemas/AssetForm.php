<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Product;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulir satu unit aset.
 *
 * Identitas barang dan data pembelian tidak lagi diketik di sini: keduanya
 * dipilih dari katalog barang dan batch pembelian. Yang tersisa adalah data
 * yang memang melekat pada unit ini sendiri.
 */
class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Unit')
                    ->description('Kode unit dibuat otomatis mengikuti kategori barangnya.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('asset_code')
                            ->label('Kode Aset')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Dibuat otomatis'),

                        Select::make('product_id')
                            ->label('Barang')
                            ->placeholder('Pilih Barang')
                            ->relationship('product')
                            ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                            ->searchable(['model'])
                            ->preload()
                            ->required()
                            ->live()
                            // Mengganti barang berarti mengganti kategori, dan kode aset
                            // sudah terlanjur mengikuti kategori lama.
                            ->disabled(fn (?Asset $record) => $record !== null)
                            ->dehydrated(),

                        TextInput::make('serial_number')
                            ->label('Nomor Seri')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText(function (Get $get): ?string {
                                $product = Product::with('category')->find($get('product_id'));

                                if (! $product?->requiresSerialNumber()) {
                                    return null;
                                }

                                return 'Boleh dikosongkan dulu, tetapi harus terisi sebelum unit ini diserahkan, dikirim antar cabang, atau ditinggal di tempat servis.';
                            }),

                        TextInput::make('imei_1')
                            ->label('IMEI 1')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => static::kategoriKode($get('product_id')) === 'SMP'),

                        TextInput::make('imei_2')
                            ->label('IMEI 2')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => static::kategoriKode($get('product_id')) === 'SMP'),

                        Select::make('condition_id')
                            ->label('Kondisi')
                            ->placeholder('Pilih Kondisi')
                            ->relationship('condition', 'name')
                            ->required(),
                    ]),

                Section::make('Pembelian')
                    ->description('Tanggal, harga, vendor, dan garansi diambil dari batch pembeliannya.')
                    ->columns(2)
                    ->schema([
                        Select::make('purchase_batch_id')
                            ->label('Batch Pembelian')
                            ->placeholder('Pilih Batch Pembelian')
                            ->relationship(
                                'purchaseBatch',
                                'invoice_number',
                                fn ($query, Get $get) => $query->when(
                                    $get('product_id'),
                                    fn ($q, $productId) => $q->where('product_id', $productId),
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record): string => trim(sprintf(
                                '%s · %s · Rp %s',
                                $record->invoice_number ?: 'Tanpa faktur',
                                $record->purchase_date?->format('d/m/Y') ?? '-',
                                number_format((float) $record->unit_price, 0, ',', '.'),
                            )))
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Kosongkan bila unit ini warisan data lama yang tidak diketahui pembeliannya.'),
                    ]),

                Section::make('Penempatan & Catatan')
                    ->columns(2)
                    ->schema([
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn () => auth()->user()->role !== Role::AdminPusat)
                            // Field terkunci tetap ikut tersimpan; tanpa ini branch_id kosong bagi admin cabang.
                            ->dehydrated(),

                        Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->columnSpanFull(),

                        KeyValue::make('specifications')
                            ->label('Spesifikasi Khusus Unit Ini')
                            ->keyLabel('Bagian')
                            ->valueLabel('Nilai')
                            ->helperText('Isi hanya bila unit ini menyimpang dari spesifikasi bawaan barangnya, misalnya setelah RAM ditambah.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function kategoriKode(mixed $productId): ?string
    {
        if (blank($productId)) {
            return null;
        }

        return Product::with('category')->find($productId)?->category?->code_prefix;
    }
}
