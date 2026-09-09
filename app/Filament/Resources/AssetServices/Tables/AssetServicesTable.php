<?php

namespace App\Filament\Resources\AssetServices\Tables;

use App\Models\Condition;
use App\Models\ServiceResult;
use App\Services\ServiceService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'open'))
            ->columns([
                TextColumn::make('asset.asset_code')
                    ->label('Kode Aset')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serviceKind.name')
                    ->label('Jenis')
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Tgl Masuk')
                    ->date()
                    ->sortable(),
                TextColumn::make('expected_at')
                    ->label('Estimasi')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Terbuka',
                        'closed' => 'Selesai',
                        default => $state,
                    }),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('selesaikan')
                    ->label('Selesaikan')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->form([
                        DatePicker::make('finished_at')
                            ->label('Tanggal Selesai')
                            ->default(now())
                            ->required(),
                        Select::make('service_result_id')
                            ->label('Hasil Servis')
                            ->options(ServiceResult::pluck('name', 'id'))
                            ->required(),
                        Textarea::make('work_done')
                            ->label('Pekerjaan yang Dilakukan'),
                        TextInput::make('cost_service')
                            ->label('Biaya Jasa')
                            ->numeric()
                            ->default(0),
                        TextInput::make('cost_parts')
                            ->label('Biaya Part')
                            ->numeric()
                            ->default(0),
                        DatePicker::make('service_warranty_until')
                            ->label('Garansi Servis Sampai'),
                        Select::make('condition_after_id')
                            ->label('Kondisi Setelah Servis')
                            ->options(Condition::pluck('name', 'id')),
                        Select::make('status_setelah')
                            ->label('Status Setelah')
                            ->options([
                                'dipakai' => 'Kembali ke Pemegang (Dipakai)',
                                'spare' => 'Ke Gudang (Spare)',
                                'rusak' => 'Tidak Bisa Diperbaiki (Rusak)',
                            ])
                            ->default('spare')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(ServiceService::class)->close($record, $data);
                            Notification::make()->success()->title('Servis selesai!')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
