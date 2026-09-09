<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Models\Asset;
use App\Models\Condition;
use App\Services\EmployeeOffboarding;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama Karyawan')
                    ->searchable(),
                TextColumn::make('position.name')
                    ->label('Jabatan')
                    ->sortable(),
                TextColumn::make('division.name')
                    ->label('Divisi')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                IconColumn::make('can_sign_as_witness')
                    ->label('Saksi')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('nonaktifkan')
                    ->label('Nonaktifkan')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->visible(fn ($record) => $record->is_active)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(EmployeeOffboarding::class)->disable($record);
                            Notification::make()->success()->title('Karyawan dinonaktifkan')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('tarik_semua')
                    ->label('Tarik Semua Aset')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn ($record) => $record->is_active && Asset::where('current_holder_id', $record->id)->exists())
                    ->form([
                        DatePicker::make('transaction_date')
                            ->label('Tanggal Penarikan')
                            ->default(now())
                            ->required(),
                        Select::make('condition_id')
                            ->label('Kondisi')
                            ->options(Condition::pluck('name', 'id'))
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(EmployeeOffboarding::class)->returnAllAssets($record, $data);
                            Notification::make()->success()->title('Semua aset ditarik')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
