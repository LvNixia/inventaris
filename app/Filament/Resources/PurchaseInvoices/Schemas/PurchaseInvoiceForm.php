<?php

namespace App\Filament\Resources\PurchaseInvoices\Schemas;

use App\Enums\Role;
use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Unique;

/**
 * Formulir tagihan vendor.
 *
 * Satu faktur boleh mencakup beberapa penerimaan, karena vendor sering menagih
 * beberapa pengiriman sekaligus. Jatuh tempo dihitung dari syarat pembayaran
 * yang disalin ke faktur, bukan dibaca ulang dari vendor.
 */
class PurchaseInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Faktur')
                    ->description('Nomor faktur berasal dari vendor. Dua vendor boleh memakai nomor yang sama.')
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
                            ->disabled(fn (?PurchaseInvoice $record) => $record !== null)
                            ->dehydrated()
                            ->afterStateUpdated(fn ($state, Set $set) => $set(
                                'payment_term_id',
                                Vendor::find($state)?->payment_term_id,
                            )),

                        TextInput::make('invoice_number')
                            ->label('Nomor Faktur')
                            ->required()
                            ->maxLength(255)
                            // Keunikannya berpasangan dengan vendor: dua vendor
                            // boleh memakai nomor yang sama. Tanpa aturan ini,
                            // nomor kembar muncul sebagai galat basis data
                            // alih-alih pesan di bawah kolomnya.
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('vendor_id', $get('vendor_id')),
                            )
                            ->validationMessages([
                                'unique' => 'Nomor faktur ini sudah tercatat untuk vendor tersebut.',
                            ]),

                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn () => auth()->user()->role !== Role::AdminPusat)
                            ->dehydrated(),

                        DatePicker::make('invoice_date')
                            ->label('Tanggal Faktur')
                            ->default(now())
                            ->required()
                            ->live(),

                        Select::make('payment_term_id')
                            ->label('Syarat Pembayaran')
                            ->placeholder('Pilih Syarat')
                            ->relationship('paymentTerm', 'name', fn ($query) => $query->where('is_active', true))
                            ->preload()
                            ->live(),

                        Placeholder::make('jatuh_tempo')
                            ->label('Jatuh Tempo')
                            ->content(function (Get $get): string {
                                $tanggal = $get('invoice_date');

                                if (blank($tanggal)) {
                                    return '—';
                                }

                                $term = PaymentTerm::find($get('payment_term_id'));

                                return $term
                                    ? $term->dueDateFrom($tanggal)->translatedFormat('d M Y').' ('.$term->days.' hari)'
                                    : Carbon::parse($tanggal)->translatedFormat('d M Y');
                            }),
                    ]),

                Section::make('Penerimaan yang Ditagih')
                    ->description('Boleh lebih dari satu. Nomor faktur ini akan tersalin ke unit aset yang lahir dari penerimaan tersebut.')
                    ->schema([
                        Select::make('goods_receipt_ids')
                            ->hiddenLabel()
                            ->multiple()
                            ->placeholder('Pilih penerimaan barang')
                            ->options(fn (Get $get): array => static::penerimaanVendor($get('vendor_id')))
                            ->searchable()
                            ->dehydrated()
                            ->helperText('Hanya penerimaan yang sudah disetujui dan berasal dari vendor yang sama.'),
                    ]),

                Section::make('Nilai Tagihan')
                    ->columns(3)
                    ->schema([
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ',']),

                        TextInput::make('tax')
                            ->label('PPN')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ',']),

                        TextInput::make('total_amount')
                            ->label('Total Tagihan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ',']),
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
     * Penerimaan milik vendor yang sudah disetujui.
     *
     * @return array<int, string>
     */
    protected static function penerimaanVendor(mixed $vendorId): array
    {
        if (blank($vendorId)) {
            return [];
        }

        return GoodsReceipt::where('vendor_id', $vendorId)
            ->where('status', 'received')
            ->orderByDesc('receipt_date')
            ->get()
            ->mapWithKeys(fn (GoodsReceipt $gr): array => [
                $gr->id => $gr->gr_number.' · '.$gr->receipt_date->translatedFormat('d M Y')
                    .' · '.$gr->items()->count().' unit',
            ])
            ->all();
    }
}
