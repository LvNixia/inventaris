<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Category;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Dasar')
                    ->description('Identitas utama aset. Kode aset dibuat otomatis mengikuti kategori.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('asset_code')
                            ->label('Kode Aset')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Dibuat otomatis')
                            ->helperText(fn (Get $get) =>
                                $get('category_id')
                                    ? 'Kode akan menjadi IDS-' . Category::find($get('category_id'))?->code_prefix . '-XXX'
                                    : ''
                            ),
                        Select::make('category_id')
                            ->label('Kategori')
                            ->placeholder('Pilih Kategori')
                            ->relationship('category', 'name')
                            ->required()
                            ->live()
                            ->disabled(fn (?object $record) => $record && ($record->qty_in > 0 || $record->qty_out > 0)),
                        Select::make('brand_id')
                            ->label('Merk')
                            ->placeholder('Pilih Merk')
                            ->relationship('brand', 'name')
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')->required()->unique('brands', 'name'),
                            ]),
                        TextInput::make('model')
                            ->label('Tipe / Model'),
                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->disabled(fn (?object $record) => $record && ($record->qty_in > 0 || $record->qty_out > 0)),
                        Select::make('condition_id')
                            ->label('Kondisi')
                            ->placeholder('Pilih Kondisi')
                            ->relationship('condition', 'name')
                            ->required()
                            ->options(function (Get $get) {
                                $category = Category::find($get('category_id'));
                                if (!$category) return \App\Models\Condition::pluck('name', 'id');

                                $appliesTo = in_array($category->code_prefix, ['LSS']) ? 'license' : 'hardware';
                                return \App\Models\Condition::whereIn('applies_to', ['all', $appliesTo])->pluck('name', 'id');
                            }),
                    ]),

                Section::make('Detail Hardware & Spesifikasi')
                    ->description('Nomor seri, IMEI, dan rincian spesifikasi teknis.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('serial_number')
                            ->label('Nomor Seri')
                            ->required(function (Get $get) {
                                $category = Category::find($get('category_id'));
                                return $category ? $category->requires_serial : false;
                            })
                            ->disabled(fn (?object $record) => $record && ($record->qty_in > 0 || $record->qty_out > 0))
                            ->unique(ignoreRecord: true),
                        TextInput::make('imei_1')
                            ->label('IMEI 1')
                            ->visible(function (Get $get) {
                                $category = Category::find($get('category_id'));
                                return $category && in_array($category->code_prefix, ['SMP', 'TAB']);
                            }),
                        TextInput::make('imei_2')
                            ->label('IMEI 2')
                            ->visible(function (Get $get) {
                                $category = Category::find($get('category_id'));
                                return $category && in_array($category->code_prefix, ['SMP', 'TAB']);
                            }),
                        // NOTE: specifications & accessories are JSON.
                        // We can use KeyValue or Repeater. We'll use KeyValue for simplicity.
                        \Filament\Forms\Components\KeyValue::make('specifications')
                            ->label('Spesifikasi')
                            ->keyLabel('Atribut (Contoh: RAM)')
                            ->valueLabel('Nilai (Contoh: 16GB)')
                            ->addActionLabel('Tambahkan baris')
                            ->columnSpanFull(),
                        \Filament\Forms\Components\KeyValue::make('accessories')
                            ->label('Aksesoris')
                            ->keyLabel('Nama Aksesoris')
                            ->valueLabel('Keterangan')
                            ->addActionLabel('Tambahkan baris')
                            ->columnSpanFull(),
                    ]),

                Section::make('Pembelian & Garansi')
                    ->description('Data pembelian untuk perhitungan nilai aset dan masa garansi.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('purchase_date')
                            ->label('Tanggal Pembelian')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->maxDate(now()),
                        Select::make('vendor_id')
                            ->label('Vendor / Toko')
                            ->placeholder('Pilih Vendor')
                            ->relationship('vendor', 'name')
                            ->createOptionForm([
                                TextInput::make('name')->required()->unique('vendors', 'name'),
                                Select::make('type')->options(['toko' => 'Toko', 'servis' => 'Servis', 'keduanya' => 'Keduanya'])->required(),
                            ]),
                        TextInput::make('invoice_number')
                            ->label('Nomor Faktur / Invoice'),
                        TextInput::make('unit_price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \'.\', \',\', 0)'))
                            ->stripCharacters('.'),
                        DatePicker::make('warranty_until')
                            ->label('Garansi Sampai')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->afterOrEqual('purchase_date'),
                    ]),

                Section::make('Penempatan & Catatan')
                    ->description('Lokasi cabang tempat aset berada.')
                    ->columns(2)
                    ->schema([
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn() => auth()->user()->branch_id)
                            ->disabled(fn() => auth()->user()->role !== \App\Enums\Role::AdminPusat)
                            // Field terkunci tetap ikut tersimpan; tanpa ini branch_id kosong bagi admin cabang.
                            ->dehydrated(),
                        Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
