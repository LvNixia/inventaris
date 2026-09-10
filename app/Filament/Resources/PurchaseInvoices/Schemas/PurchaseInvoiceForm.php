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
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
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
                            // Penerimaan yang sudah terpilih ikut dikosongkan:
                            // isinya milik vendor sebelumnya dan akan ditolak
                            // service saat disimpan.
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('payment_term_id', Vendor::find($state)?->payment_term_id);
                                $set('goods_receipt_ids', []);
                                $set('subtotal', 0);
                                $set('total_amount', 0);
                            }),

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
                    ->description('Boleh lebih dari satu. Nilai tagihan di bawah ikut terisi dari penerimaan yang dipilih, dan nomor faktur ini tersalin ke unit aset yang lahir darinya.')
                    ->schema([
                        Select::make('goods_receipt_ids')
                            ->hiddenLabel()
                            ->multiple()
                            ->placeholder('Pilih penerimaan barang')
                            ->options(fn (Get $get, ?PurchaseInvoice $record): array => static::penerimaanVendor(
                                $get('vendor_id'),
                                $record?->id,
                            ))
                            ->searchable()
                            ->live()
                            ->dehydrated()
                            ->helperText('Hanya penerimaan yang sudah disetujui, berasal dari vendor yang sama, dan belum ditagih faktur lain.')
                            // Subtotal ditarik dari harga unit tiap penerimaan.
                            // PPN tetap diisi manual karena angkanya di faktur
                            // vendor sering dibulatkan berbeda.
                            ->afterStateUpdated(fn ($state, Get $get, Set $set) => static::hitungNilai($state, $get, $set)),

                        Placeholder::make('rincian_penerimaan')
                            ->label('Rincian')
                            ->content(fn (Get $get): HtmlString => static::rincian($get('goods_receipt_ids'))),
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
                            ->stripCharacters(['.', ','])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::hitungTotal($get, $set))
                            ->helperText('Terisi otomatis dari penerimaan; ubah bila faktur vendor berbeda.'),

                        TextInput::make('tax')
                            ->label('PPN')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ','])
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::hitungTotal($get, $set)),

                        TextInput::make('total_amount')
                            ->label('Total Tagihan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
                            ->stripCharacters(['.', ','])
                            ->helperText('Subtotal ditambah PPN. Boleh disesuaikan bila faktur vendor berbeda.'),
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
     * Penerimaan milik vendor yang sudah disetujui dan belum ditagih.
     *
     * Penerimaan yang sudah tertaut ke faktur yang sedang dibuka tetap ikut,
     * kalau tidak isian yang sudah tersimpan tampil kosong saat diedit.
     *
     * @return array<int, string>
     */
    protected static function penerimaanVendor(mixed $vendorId, ?int $invoiceId = null): array
    {
        if (blank($vendorId)) {
            return [];
        }

        return static::ambilPenerimaan($vendorId, $invoiceId)
            ->mapWithKeys(fn (GoodsReceipt $gr): array => [
                $gr->id => $gr->gr_number.' · '.$gr->receipt_date->translatedFormat('d M Y')
                    .' · '.$gr->items->count().' unit · Rp '.number_format($gr->total_value, 0, ',', '.'),
            ])
            ->all();
    }

    /**
     * @return Collection<int, GoodsReceipt>
     */
    protected static function ambilPenerimaan(mixed $vendorId, ?int $invoiceId = null): Collection
    {
        return GoodsReceipt::query()
            ->where('vendor_id', $vendorId)
            ->where('status', 'received')
            ->belumDitagih($invoiceId)
            ->with('items.purchaseOrderItem')
            ->orderByDesc('receipt_date')
            ->get();
    }

    /**
     * Jumlahkan nilai penerimaan terpilih ke subtotal, lalu hitung totalnya.
     *
     * @param  array<int, int|string>|null  $ids
     */
    protected static function hitungNilai(mixed $ids, Get $get, Set $set): void
    {
        $set('subtotal', static::nilaiTerpilih($ids));

        static::hitungTotal($get, $set);
    }

    protected static function hitungTotal(Get $get, Set $set): void
    {
        $set('total_amount', (float) $get('subtotal') + (float) $get('tax'));
    }

    /**
     * @param  array<int, int|string>|null  $ids
     */
    protected static function nilaiTerpilih(mixed $ids): float
    {
        return static::penerimaanTerpilih($ids)->sum(fn (GoodsReceipt $gr): float => $gr->total_value);
    }

    /**
     * Penerimaan terpilih, apa pun statusnya sekarang.
     *
     * Dibaca tanpa saringan vendor supaya rincian tetap tampil untuk faktur
     * lama, bahkan jika penerimaannya kemudian dibatalkan.
     *
     * @param  array<int, int|string>|null  $ids
     * @return Collection<int, GoodsReceipt>
     */
    protected static function penerimaanTerpilih(mixed $ids): Collection
    {
        $ids = array_filter((array) $ids);

        if ($ids === []) {
            return collect();
        }

        return GoodsReceipt::query()
            ->whereIn('id', $ids)
            ->with('items.purchaseOrderItem')
            ->orderByDesc('receipt_date')
            ->get();
    }

    /**
     * @param  array<int, int|string>|null  $ids
     */
    protected static function rincian(mixed $ids): HtmlString
    {
        $penerimaan = static::penerimaanTerpilih($ids);

        if ($penerimaan->isEmpty()) {
            return new HtmlString('<span class="text-sm text-gray-500">Belum ada penerimaan dipilih.</span>');
        }

        $baris = $penerimaan
            ->map(fn (GoodsReceipt $gr): string => '<li>'
                .e($gr->gr_number ?? 'Draf #'.$gr->id)
                .' — '.$gr->items->count().' unit — Rp '
                .number_format($gr->total_value, 0, ',', '.')
                .'</li>')
            ->join('');

        $total = number_format($penerimaan->sum(fn (GoodsReceipt $gr): float => $gr->total_value), 0, ',', '.');

        return new HtmlString(
            '<ul class="list-disc ps-5 text-sm">'.$baris.'</ul>'
            .'<p class="mt-2 text-sm font-medium">Jumlah nilai penerimaan: Rp '.$total.'</p>'
        );
    }
}
