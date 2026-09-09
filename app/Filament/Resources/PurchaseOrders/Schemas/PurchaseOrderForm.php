<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\Role;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\RawJs;

/**
 * Formulir pesanan pembelian.
 *
 * Isinya hanya bisa diubah selama berstatus draf. Setelah diajukan, seluruh
 * bidang dikunci agar isi yang disetujui sama persis dengan yang dikirim ke
 * vendor.
 */
class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Pesanan')
                    ->description('Vendor dan syarat pembayaran disalin ke PO, sehingga perubahan data vendor tidak mengubah PO yang sudah jalan.')
                    ->columns(3)
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->placeholder('Pilih Vendor')
                            ->relationship('vendor', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (?PurchaseOrder $record) => $record && ! $record->isEditable())
                            ->dehydrated()
                            // Syarat bayar mengikuti vendor, tapi tetap boleh diubah per PO.
                            ->afterStateUpdated(fn ($state, Set $set) => $set(
                                'payment_term_id',
                                Vendor::find($state)?->payment_term_id,
                            )),

                        Select::make('payment_term_id')
                            ->label('Syarat Pembayaran')
                            ->placeholder('Pilih Syarat')
                            ->relationship('paymentTerm', 'name', fn ($query) => $query->where('is_active', true))
                            ->preload()
                            ->disabled(fn (?PurchaseOrder $record) => $record && ! $record->isEditable())
                            ->dehydrated(),

                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn (?PurchaseOrder $record) => auth()->user()->role !== Role::AdminPusat
                                || ($record && ! $record->isEditable()))
                            ->dehydrated(),

                        DatePicker::make('po_date')
                            ->label('Tanggal PO')
                            ->default(now())
                            ->required()
                            ->disabled(fn (?PurchaseOrder $record) => $record && ! $record->isEditable())
                            ->dehydrated(),

                        DatePicker::make('expected_date')
                            ->label('Estimasi Datang')
                            ->afterOrEqual('po_date')
                            ->disabled(fn (?PurchaseOrder $record) => $record && ! $record->isEditable())
                            ->dehydrated(),

                        TextInput::make('po_number')
                            ->label('Nomor PO')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Terbit saat disetujui'),
                    ]),

                Section::make('Barang Dipesan')
                    ->description('Satu baris untuk satu jenis barang. Nilai total dihitung ulang saat disimpan.')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->addActionLabel('Tambah Barang')
                            ->minItems(1)
                            ->reorderable(false)
                            ->disabled(fn (?PurchaseOrder $record) => $record && ! $record->isEditable())
                            ->table([
                                TableColumn::make('Barang')
                                    ->width('34%')
                                    ->verticalAlignment(VerticalAlignment::Center)
                                    ->markAsRequired(),
                                TableColumn::make('Jumlah')
                                    ->width('12%')
                                    ->verticalAlignment(VerticalAlignment::Center)
                                    ->markAsRequired(),
                                TableColumn::make('Harga Satuan')
                                    ->width('20%')
                                    ->verticalAlignment(VerticalAlignment::Center)
                                    ->markAsRequired(),
                                TableColumn::make('PPN %')
                                    ->width('10%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                                TableColumn::make('Garansi (bln)')
                                    ->width('12%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                                TableColumn::make('Jumlah Harga')
                                    ->width('12%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                            ])
                            ->schema([
                                Select::make('product_id')
                                    ->hiddenLabel()
                                    ->placeholder('Pilih Barang')
                                    ->relationship('product', 'model', fn ($query) => $query->where('is_active', true))
                                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                                    ->searchable(['model'])
                                    ->preload()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                TextInput::make('quantity')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true),

                                TextInput::make('unit_price')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                                    ->stripCharacters(['.', ','])
                                    ->live(onBlur: true),

                                TextInput::make('tax_percent')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(0)
                                    ->suffix('%')
                                    ->live(onBlur: true),

                                TextInput::make('warranty_months')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('—'),

                                // Hanya tampilan; nilai sebenarnya dihitung ulang
                                // oleh PurchaseOrderService saat disimpan.
                                TextInput::make('total_price')
                                    ->hiddenLabel()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->prefix('Rp')
                                    ->formatStateUsing(fn ($state, Get $get): string => number_format(
                                        (float) $get('unit_price') * (int) $get('quantity'),
                                        0, ',', '.',
                                    )),
                            ]),
                    ]),

                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(3)
                            ->placeholder('Keterangan untuk vendor atau catatan internal.'),
                    ]),
            ]);
    }
}
