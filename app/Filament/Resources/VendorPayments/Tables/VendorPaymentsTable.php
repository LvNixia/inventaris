<?php

namespace App\Filament\Resources\VendorPayments\Tables;

use App\Enums\Role;
use App\Models\VendorPayment;
use App\Services\VendorPaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorPaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('Nomor')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Nilai')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('allocations_count')
                    ->label('Faktur')
                    ->counts('allocations')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => VendorPayment::METODE[$state] ?? $state),
                TextColumn::make('reference_number')
                    ->label('Nomor Bukti')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('cancelled_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (VendorPayment $record): string => $record->isCancelled() ? 'Dibatalkan' : 'Sah')
                    ->color(fn (VendorPayment $record): string => $record->isCancelled() ? 'danger' : 'success'),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_method')
                    ->label('Metode')
                    ->options(VendorPayment::METODE),
                Filter::make('sah')
                    ->label('Sembunyikan yang dibatalkan')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->active()),
            ])
            ->recordActions([
                Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VendorPayment $record): bool => ! $record->isCancelled())
                    ->schema([
                        Textarea::make('alasan')
                            ->label('Alasan Pembatalan')
                            ->helperText('Alokasi ditarik dan status faktur dihitung ulang. Nomor pembayaran tetap tersimpan.')
                            ->required(),
                    ])
                    ->action(function (VendorPayment $record, array $data) {
                        try {
                            app(VendorPaymentService::class)->cancel($record, $data['alasan']);

                            Notification::make()->success()->title('Pembayaran dibatalkan')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
            ]);
    }
}
