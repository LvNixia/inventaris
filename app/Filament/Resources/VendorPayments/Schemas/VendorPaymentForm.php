<?php

namespace App\Filament\Resources\VendorPayments\Schemas;

use App\Enums\Role;
use App\Models\PurchaseInvoice;
use App\Models\VendorPayment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
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
 * Formulir pembayaran vendor.
 *
 * Satu pembayaran dialokasikan ke satu atau beberapa faktur. Jumlah alokasi
 * harus sama persis dengan nilai pembayaran; pemeriksaannya ada di
 * VendorPaymentService supaya berlaku juga di luar antarmuka.
 */
class VendorPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Pembayaran')
                    ->columns(3)
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->placeholder('Pilih Vendor')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (?VendorPayment $record) => $record !== null)
                            ->dehydrated()
                            // Mengganti vendor membuat alokasi lama tidak berlaku.
                            ->afterStateUpdated(fn (Set $set) => $set('allocations', [])),

                        DatePicker::make('payment_date')
                            ->label('Tanggal Bayar')
                            ->default(now())
                            ->maxDate(now())
                            ->required(),

                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn () => auth()->user()->role !== Role::AdminPusat)
                            ->dehydrated(),

                        TextInput::make('amount')
                            ->label('Nilai Pembayaran')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->live(onBlur: true)
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ',']),

                        Select::make('payment_method')
                            ->label('Metode')
                            ->options(VendorPayment::METODE)
                            ->default('transfer')
                            ->required(),

                        TextInput::make('reference_number')
                            ->label('Nomor Bukti')
                            ->placeholder('Nomor transfer atau giro'),
                    ]),

                Section::make('Alokasi ke Faktur')
                    ->description('Satu pembayaran boleh melunasi beberapa faktur. Jumlah alokasi harus sama dengan nilai pembayaran.')
                    ->schema([
                        Repeater::make('allocations')
                            ->hiddenLabel()
                            ->addActionLabel('Tambah Faktur')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->live()
                            ->table([
                                TableColumn::make('Faktur')
                                    ->width('60%')
                                    ->verticalAlignment(VerticalAlignment::Center)
                                    ->markAsRequired(),
                                TableColumn::make('Dialokasikan')
                                    ->width('40%')
                                    ->verticalAlignment(VerticalAlignment::Center)
                                    ->markAsRequired(),
                            ])
                            ->schema([
                                Select::make('purchase_invoice_id')
                                    ->hiddenLabel()
                                    ->placeholder('Pilih Faktur')
                                    ->options(fn (Get $get): array => static::fakturBelumLunas($get('../../vendor_id')))
                                    ->required()
                                    ->distinct()
                                    ->searchable()
                                    ->live()
                                    // Bawaannya melunasi sisa faktur.
                                    ->afterStateUpdated(fn ($state, Set $set) => $set(
                                        'amount',
                                        PurchaseInvoice::find($state)?->outstanding,
                                    )),

                                TextInput::make('amount')
                                    ->hiddenLabel()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                                    ->stripCharacters(['.', ',']),
                            ]),

                        Placeholder::make('selisih')
                            ->label('Pemeriksaan')
                            ->content(function (Get $get): string {
                                $nilai = (float) $get('amount');
                                $total = collect($get('allocations') ?? [])
                                    ->sum(fn ($a): float => (float) ($a['amount'] ?? 0));

                                if (abs($total - $nilai) <= 0.5) {
                                    return 'Alokasi sudah pas: Rp '.number_format($total, 0, ',', '.');
                                }

                                return sprintf(
                                    'Belum pas — alokasi Rp %s, pembayaran Rp %s, selisih Rp %s.',
                                    number_format($total, 0, ',', '.'),
                                    number_format($nilai, 0, ',', '.'),
                                    number_format(abs($total - $nilai), 0, ',', '.'),
                                );
                            }),
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
     * Faktur vendor yang masih menyisakan tagihan.
     *
     * @return array<int, string>
     */
    protected static function fakturBelumLunas(mixed $vendorId): array
    {
        if (blank($vendorId)) {
            return [];
        }

        return PurchaseInvoice::where('vendor_id', $vendorId)
            ->outstanding()
            ->orderBy('due_date')
            ->get()
            ->mapWithKeys(fn (PurchaseInvoice $inv): array => [
                $inv->id => $inv->invoice_number
                    .' · jatuh tempo '.$inv->due_date->translatedFormat('d M Y')
                    .' · sisa Rp '.number_format($inv->outstanding, 0, ',', '.'),
            ])
            ->all();
    }
}
