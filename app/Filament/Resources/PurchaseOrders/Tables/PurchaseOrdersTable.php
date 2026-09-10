<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\Role;
use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrdersTable
{
    /**
     * Label status dipakai bersama oleh kolom dan penyaring.
     */
    public const STATUS = [
        'draft' => 'Draf',
        'pending_approval' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'partial_receipt' => 'Diterima Sebagian',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Belum terbit'),
                TextColumn::make('po_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Baris')
                    ->counts('items')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_approval' => 'warning',
                        'approved' => 'info',
                        'partial_receipt' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expected_date')
                    ->label('Estimasi Datang')
                    ->date('d M Y')
                    ->toggleable(),
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
                Filter::make('perlu_persetujuan')
                    ->label('Menunggu persetujuan saya')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('status', 'pending_approval')),
            ])
            ->recordActions([
                Action::make('ajukan')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Ajukan PO untuk disetujui')
                    ->modalDescription('Setelah diajukan, isinya tidak bisa diubah lagi kecuali dikembalikan ke draf.')
                    ->action(fn (PurchaseOrder $record) => static::jalankan(
                        fn () => app(PurchaseOrderService::class)->submit($record),
                        'PO diajukan',
                    )),

                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === 'pending_approval'
                        && auth()->user()->role === Role::AdminPusat)
                    ->requiresConfirmation()
                    ->modalHeading('Setujui PO')
                    ->modalDescription('Nomor PO akan terbit dan pesanan siap dikirim ke vendor.')
                    ->action(fn (PurchaseOrder $record) => static::jalankan(
                        fn () => app(PurchaseOrderService::class)->approve($record),
                        'PO disetujui',
                    )),

                Action::make('kembalikan')
                    ->label('Kembalikan ke Draf')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === 'pending_approval'
                        && auth()->user()->role === Role::AdminPusat)
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Dikembalikan')
                            ->required(),
                    ])
                    ->action(fn (PurchaseOrder $record, array $data) => static::jalankan(
                        fn () => app(PurchaseOrderService::class)->returnToDraft($record, $data['alasan']),
                        'PO dikembalikan ke draf',
                    )),

                Action::make('terimaBarang')
                    ->label('Buat Penerimaan')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    // Sisa unitnya ditarik otomatis di halaman penerimaan, jadi
                    // pengguna tidak perlu mengetik ulang barang dan harganya.
                    ->visible(fn (PurchaseOrder $record): bool => in_array(
                        $record->status,
                        ['approved', 'partial_receipt'],
                        true,
                    ))
                    ->url(fn (PurchaseOrder $record): string => GoodsReceiptResource::getUrl('create', [
                        'purchase_order_id' => $record->id,
                    ])),

                Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PurchaseOrder $record): bool => ! in_array($record->status, ['cancelled', 'completed'], true))
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(fn (PurchaseOrder $record, array $data) => static::jalankan(
                        fn () => app(PurchaseOrderService::class)->cancel($record, $data['alasan']),
                        'PO dibatalkan',
                    )),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Menjalankan satu aksi, lalu melaporkan hasilnya lewat notifikasi.
     */
    protected static function jalankan(callable $aksi, string $judulSukses): void
    {
        try {
            $aksi();

            Notification::make()->success()->title($judulSukses)->send();
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
        }
    }
}
