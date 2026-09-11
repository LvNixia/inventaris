<?php

namespace App\Filament\Resources\GoodsReceipts\Tables;

use App\Enums\Role;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\GoodsReceipt;
use App\Services\GoodsReceiptService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GoodsReceiptsTable
{
    public const STATUS = [
        'draft' => 'Draf',
        'received' => 'Diterima',
        'cancelled' => 'Dibatalkan',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gr_number')
                    ->label('Nomor')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Belum terbit'),
                TextColumn::make('receipt_date')
                    ->label('Tanggal Terima')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('purchaseOrder.po_number')
                    ->label('Pesanan')
                    ->placeholder('Tanpa pesanan')
                    ->searchable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Unit')
                    ->counts('items')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('delivery_document_number')
                    ->label('Surat Jalan')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'received' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUS),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
            ])
            ->recordActions([
                Action::make('terima')
                    ->label('Setujui Penerimaan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (GoodsReceipt $record): bool => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui penerimaan barang')
                    // Selisih harga terhadap pesanan disebutkan sebelum
                    // disetujui, bukan sesudah: yang menyetujui perlu tahu
                    // realisasinya meleset saat masih bisa membatalkan.
                    ->modalDescription(fn (GoodsReceipt $record): string => trim(
                        'Unit aset akan dibuat sebanyak baris pada penerimaan ini, lengkap dengan kode dan harganya. '
                        .($record->peringatanSelisihHarga() ?? '')
                    ))
                    ->action(fn (GoodsReceipt $record) => static::jalankan(
                        fn () => app(GoodsReceiptService::class)->receive($record),
                        'Penerimaan disetujui',
                        $record->peringatanSelisihHarga(),
                    )),

                Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (GoodsReceipt $record): bool => $record->status === 'received')
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Pembatalan')
                            ->helperText('Unit yang lahir dari penerimaan ini akan dihapus. Hanya bisa selama unitnya belum dipakai.')
                            ->required(),
                    ])
                    ->action(fn (GoodsReceipt $record, array $data) => static::jalankan(
                        fn () => app(GoodsReceiptService::class)->cancel($record, $data['alasan']),
                        'Penerimaan dibatalkan',
                    )),

                Action::make('buatFaktur')
                    ->label('Buat Faktur')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('primary')
                    // Hanya untuk penerimaan yang sudah disetujui dan belum
                    // ditagih; nilai fakturnya ikut terisi dari harga unitnya.
                    ->visible(fn (GoodsReceipt $record): bool => $record->status === 'received'
                        && GoodsReceipt::query()->bisaDitagih()->whereKey($record->id)->exists())
                    ->url(fn (GoodsReceipt $record): string => PurchaseInvoiceResource::getUrl('create', [
                        'goods_receipt_id' => $record->id,
                    ])),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Pesan penolakan bisa memuat beberapa baris sekaligus, jadi ditampilkan
     * apa adanya supaya seluruh kekurangan terbaca.
     */
    protected static function jalankan(callable $aksi, string $judulSukses, ?string $catatan = null): void
    {
        try {
            $aksi();

            Notification::make()
                ->success()
                ->title($judulSukses)
                ->body($catatan)
                ->when($catatan !== null, fn (Notification $notifikasi) => $notifikasi->persistent())
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }
}
