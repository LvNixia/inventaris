<?php

namespace App\Filament\Resources\PurchaseInvoices\Tables;

use App\Enums\Role;
use App\Models\PurchaseInvoice;
use App\Services\PurchaseInvoiceService;
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

class PurchaseInvoicesTable
{
    public const STATUS = [
        'unpaid' => 'Belum Dibayar',
        'partial' => 'Dibayar Sebagian',
        'paid' => 'Lunas',
        'cancelled' => 'Dibatalkan',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Nomor Faktur')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable()
                    // Merah bila lewat tempo dan belum lunas.
                    ->color(fn (PurchaseInvoice $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (PurchaseInvoice $record): ?string => $record->isOverdue()
                        ? 'Lewat '.$record->age_in_days.' hari'
                        : null),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Terbayar')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('outstanding')
                    ->label('Sisa')
                    ->state(fn (PurchaseInvoice $record): float => $record->outstanding)
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'warning',
                        'partial' => 'info',
                        'paid' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
            ])
            ->defaultSort('due_date')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUS),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('lewat_tempo')
                    ->label('Lewat jatuh tempo')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                Filter::make('belum_lunas')
                    ->label('Belum lunas')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->outstanding()),
            ])
            ->recordActions([
                Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PurchaseInvoice $record): bool => $record->status !== 'cancelled')
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function (PurchaseInvoice $record, array $data) {
                        try {
                            app(PurchaseInvoiceService::class)->cancel($record, $data['alasan']);

                            Notification::make()->success()->title('Faktur dibatalkan')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
