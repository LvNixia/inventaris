<?php

namespace App\Filament\Resources\GoodsReceipts\Schemas;

use App\Enums\Role;
use App\Models\Condition;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\RawJs;
use Illuminate\Support\Str;

/**
 * Formulir penerimaan barang.
 *
 * Satu baris untuk satu unit fisik. Nomor seri boleh dikosongkan selama masih
 * draf — pengisiannya baru diwajibkan saat penerimaan disetujui, sehingga draf
 * bisa disimpan setengah jadi dan dilanjutkan belakangan.
 */
class GoodsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Penerimaan')
                    ->description('Pesanan boleh dikosongkan untuk pembelian mendadak, hibah, atau retur vendor.')
                    ->columns(3)
                    ->schema([
                        Select::make('purchase_order_id')
                            ->label('Pesanan Pembelian')
                            ->placeholder('Tanpa pesanan')
                            ->options(fn (?GoodsReceipt $record): array => static::pesananTersedia($record))
                            ->searchable()
                            ->live()
                            ->disabled(fn (?GoodsReceipt $record) => $record && ! $record->isEditable())
                            ->dehydrated()
                            // Cabang dan vendor mengikuti pesanan agar tidak salah isi.
                            // Baris yang telanjur diisi ikut dikosongkan karena baris
                            // itu menunjuk ke baris pesanan yang lama.
                            ->afterStateUpdated(function ($state, Set $set) {
                                $po = PurchaseOrder::find($state);

                                if ($po) {
                                    $set('branch_id', $po->branch_id);
                                    $set('vendor_id', $po->vendor_id);
                                }

                                $set('items', []);
                            }),

                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->placeholder('Pilih Vendor')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn (?GoodsReceipt $record) => $record && ! $record->isEditable())
                            ->dehydrated(),

                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn (?GoodsReceipt $record) => auth()->user()->role !== Role::AdminPusat
                                || ($record && ! $record->isEditable()))
                            ->dehydrated(),

                        DatePicker::make('receipt_date')
                            ->label('Tanggal Terima')
                            ->default(now())
                            ->maxDate(now())
                            ->required()
                            ->disabled(fn (?GoodsReceipt $record) => $record && ! $record->isEditable())
                            ->dehydrated(),

                        TextInput::make('delivery_document_number')
                            ->label('Nomor Surat Jalan')
                            ->disabled(fn (?GoodsReceipt $record) => $record && ! $record->isEditable())
                            ->dehydrated(),

                        TextInput::make('gr_number')
                            ->label('Nomor Penerimaan')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Terbit saat disetujui'),
                    ]),

                Section::make('Unit yang Diterima')
                    ->description('Satu baris untuk satu unit. Nomor seri boleh menyusul selama masih draf, tetapi wajib terisi sebelum disetujui.')
                    ->schema([
                        Actions::make([
                            Action::make('tarikDariPesanan')
                                ->label('Tarik Sisa Pesanan')
                                ->icon('heroicon-o-arrow-down-on-square')
                                ->color('gray')
                                ->visible(fn (Get $get, ?GoodsReceipt $record): bool => filled($get('purchase_order_id'))
                                    && (! $record || $record->isEditable()))
                                ->requiresConfirmation()
                                ->modalHeading('Tarik baris dari pesanan')
                                ->modalDescription('Isi tabel di bawah akan diganti dengan sisa unit yang belum diterima dari pesanannya, lengkap dengan harga dan garansinya. Nomor seri tetap diisi manual.')
                                ->modalSubmitActionLabel('Tarik')
                                ->action(function (Get $get, Set $set): void {
                                    $baris = static::barisSisaPesanan($get('purchase_order_id'));

                                    if ($baris === []) {
                                        Notification::make()
                                            ->warning()
                                            ->title('Tidak ada sisa')
                                            ->body('Seluruh unit pada pesanan itu sudah diterima.')
                                            ->send();

                                        return;
                                    }

                                    $set('items', $baris);

                                    Notification::make()
                                        ->success()
                                        ->title(count($baris).' baris ditarik')
                                        ->body('Lengkapi nomor seri tiap unit sebelum penerimaan disetujui.')
                                        ->send();
                                }),
                        ])->key('aksiPenerimaan'),

                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->addActionLabel('Tambah Unit')
                            ->minItems(1)
                            ->reorderable(false)
                            ->disabled(fn (?GoodsReceipt $record) => $record && ! $record->isEditable())
                            ->table([
                                TableColumn::make('Barang')
                                    ->width('30%')
                                    ->verticalAlignment(VerticalAlignment::Center)
                                    ->markAsRequired(),
                                TableColumn::make('Nomor Seri / Kunci')
                                    ->width('24%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                                TableColumn::make('Harga Satuan')
                                    ->width('18%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                                TableColumn::make('Garansi (bln)')
                                    ->width('12%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                                TableColumn::make('Kondisi')
                                    ->width('16%')
                                    ->verticalAlignment(VerticalAlignment::Center),
                            ])
                            ->schema([
                                Select::make('purchase_order_item_id')
                                    ->hiddenLabel()
                                    ->placeholder('Pilih Barang')
                                    ->options(fn (Get $get): array => static::barangPesanan(
                                        $get('../../purchase_order_id'),
                                        $get('purchase_order_item_id'),
                                    ))
                                    ->visible(fn (Get $get): bool => filled($get('../../purchase_order_id')))
                                    ->required(fn (Get $get): bool => filled($get('../../purchase_order_id')))
                                    ->live()
                                    // Barang, harga, dan garansi mengikuti baris pesanan.
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $poItem = PurchaseOrderItem::find($state);

                                        if ($poItem) {
                                            $set('product_id', $poItem->product_id);
                                            $set('unit_price', $poItem->unit_price);
                                            $set('warranty_months', $poItem->warranty_months);
                                        }
                                    }),

                                Select::make('product_id')
                                    ->hiddenLabel()
                                    ->placeholder('Pilih Barang')
                                    ->relationship('product', 'model', fn ($query) => $query->where('is_active', true))
                                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name)
                                    ->searchable(['model'])
                                    ->preload()
                                    ->required()
                                    ->live()
                                    // Pada GR dengan pesanan, barangnya ditentukan baris
                                    // pesanan sehingga kolomnya disembunyikan. Nilainya
                                    // tetap harus ikut tersimpan: tanpa
                                    // `dehydratedWhenHidden`, `product_id` hilang saat
                                    // disimpan dan penyimpanan gagal di basis data.
                                    ->visible(fn (Get $get): bool => blank($get('../../purchase_order_id')))
                                    ->dehydrated()
                                    ->dehydratedWhenHidden(),

                                TextInput::make('serial_number')
                                    ->hiddenLabel()
                                    ->placeholder(fn (Get $get): string => static::wajibSerial($get('product_id'))
                                        ? 'Wajib sebelum disetujui'
                                        : 'Tidak diperlukan')
                                    ->distinct()
                                    ->unique('assets', 'serial_number'),

                                TextInput::make('unit_price')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                                    ->stripCharacters(['.', ',']),

                                TextInput::make('warranty_months')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('—'),

                                Select::make('condition_id')
                                    ->hiddenLabel()
                                    ->placeholder('Baik')
                                    ->relationship('condition', 'name'),
                            ]),
                    ]),

                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->hiddenLabel()
                            ->rows(2),
                    ]),
            ]);
    }

    /**
     * Pesanan yang boleh dipilih.
     *
     * Pesanan milik penerimaan yang sedang dibuka selalu ikut, sekalipun
     * statusnya sudah `completed` atau `cancelled`. Tanpa itu kolomnya tampil
     * kosong pada penerimaan lama, seolah-olah tidak berasal dari pesanan mana
     * pun.
     *
     * @return array<int, string>
     */
    protected static function pesananTersedia(?GoodsReceipt $record): array
    {
        $terpilih = $record?->purchase_order_id;

        return PurchaseOrder::query()
            ->where(fn ($query) => $query
                ->whereIn('status', ['approved', 'partial_receipt'])
                ->when($terpilih, fn ($q) => $q->orWhere('id', $terpilih)))
            ->with('vendor')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (PurchaseOrder $po): array => [
                $po->id => ($po->po_number ?? 'Draf #'.$po->id).' · '.$po->vendor?->name,
            ])
            ->all();
    }

    /**
     * Baris pesanan yang masih menyisakan unit untuk diterima.
     *
     * Baris yang sudah penuh disembunyikan supaya tidak terpilih lagi, kecuali
     * baris yang sedang dipakai baris penerimaan ini — kalau ikut hilang,
     * isian yang sudah ada tampil kosong.
     *
     * @return array<int, string>
     */
    protected static function barangPesanan(mixed $poId, mixed $terpilih = null): array
    {
        if (blank($poId)) {
            return [];
        }

        return PurchaseOrderItem::where('purchase_order_id', $poId)
            ->with('product.brand')
            ->get()
            ->filter(fn (PurchaseOrderItem $item): bool => static::sisaPesanan($item) > 0
                || (int) $item->id === (int) $terpilih)
            ->mapWithKeys(function (PurchaseOrderItem $item): array {
                $sisa = static::sisaPesanan($item);

                return [$item->id => $item->product?->name." (sisa {$sisa} dari {$item->quantity})"];
            })
            ->all();
    }

    /**
     * Satu baris formulir untuk tiap unit pesanan yang belum diterima.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function barisSisaPesanan(mixed $poId): array
    {
        if (blank($poId)) {
            return [];
        }

        $kondisiBaik = Condition::where('code', 'baik')->value('id');
        $baris = [];

        foreach (PurchaseOrderItem::where('purchase_order_id', $poId)->get() as $item) {
            $sisa = static::sisaPesanan($item);

            for ($i = 0; $i < $sisa; $i++) {
                $baris[(string) Str::uuid()] = [
                    'purchase_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'serial_number' => null,
                    'unit_price' => $item->unit_price,
                    'warranty_months' => $item->warranty_months,
                    'condition_id' => $kondisiBaik,
                ];
            }
        }

        return $baris;
    }

    /**
     * Sisa unit pada satu baris pesanan.
     */
    protected static function sisaPesanan(PurchaseOrderItem $item): int
    {
        return max(0, (int) $item->quantity - $item->received_quantity);
    }

    protected static function wajibSerial(mixed $productId): bool
    {
        if (blank($productId)) {
            return false;
        }

        return (bool) Product::with('category')->find($productId)?->requiresSerialNumber();
    }
}
