<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;


    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            \Filament\Actions\Action::make('tarik_kembali')
                ->label('Tarik Kembali')
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $this->record->qty_out > $this->record->qty_in)
                ->form([
                    \Filament\Forms\Components\Select::make('from_employee_id')
                        ->label('Dari')
                        ->options(function () {
                            if ($this->record->quantity == 1) {
                                return [$this->record->current_holder_id => $this->record->currentHolder?->name];
                            }
                            return \App\Models\Employee::whereHas('assetTransactionsTo', function($q) {
                                $q->where('asset_id', $this->record->id)->where('stock_direction', 'out');
                            })->pluck('name', 'id');
                        })
                        ->default(fn() => $this->record->quantity == 1 ? $this->record->current_holder_id : null)
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->maxValue(fn() => $this->record->quantity == 1 ? 1 : $this->record->qty_out - $this->record->qty_in),
                    \Filament\Forms\Components\Select::make('condition_after_id')
                        ->label('Kondisi Saat Diterima')
                        ->options(\App\Models\Condition::pluck('name', 'id')),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Catatan'),
                ])
                ->action(function (array $data) {
                    try {
                        app(\App\Services\TransactionService::class)->return($this->record, $data);
                        \Filament\Notifications\Notification::make()->success()->title('Berhasil ditarik')->send();
                        $this->refreshFormData(['qty_available', 'qty_in', 'qty_out', 'current_holder_id', 'current_status_id']);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                    }
                }),

            \Filament\Actions\Action::make('ubah_status')
                ->label('Ubah Status')
                ->color('info')
                ->icon('heroicon-o-tag')
                ->form([
                    \Filament\Forms\Components\Select::make('status_id')
                        ->label('Status Baru')
                        ->options(function () {
                            $query = \App\Models\AssetStatus::query();
                            if ($this->record->current_holder_id) {
                                $query->whereIn('code', ['service', 'broken', 'registered', 'in_use']);
                            } else {
                                $query->whereIn('code', ['service', 'broken', 'registered', 'spare']);
                            }
                            return $query->pluck('name', 'id');
                        })
                        ->required(),
                    \Filament\Forms\Components\Select::make('condition_after_id')
                        ->label('Kondisi Baru (Opsional)')
                        ->options(\App\Models\Condition::pluck('name', 'id')),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Catatan'),
                ])
                ->action(function (array $data) {
                    try {
                        app(\App\Services\TransactionService::class)->changeStatus($this->record, $data);
                        \Filament\Notifications\Notification::make()->success()->title('Status diubah')->send();
                        $this->refreshFormData(['current_status_id']);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                    }
                }),

            \Filament\Actions\Action::make('dilepas')
                ->label('Dilepas / Dijual')
                ->color('danger')
                ->icon('heroicon-o-archive-box-x-mark')
                ->visible(fn () => $this->record->qty_available > 0 || $this->record->current_holder_id)
                ->form([
                    \Filament\Forms\Components\TextInput::make('quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->default(1)
                        ->required(),
                    \Filament\Forms\Components\Select::make('disposal_reason_id')
                        ->label('Alasan')
                        ->options(\App\Models\DisposalReason::pluck('name', 'id'))
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Catatan Tambahan (Misal: Harga jual, dll)'),
                ])
                ->action(function (array $data) {
                    try {
                        app(\App\Services\TransactionService::class)->dispose($this->record, $data);
                        \Filament\Notifications\Notification::make()->success()->title('Aset dilepas')->send();
                        $this->refreshFormData(['qty_available', 'qty_writeoff']);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                    }
                }),

            \Filament\Actions\Action::make('pindah_cabang')
                ->label('Pindah Cabang')
                ->color('primary')
                ->icon('heroicon-o-truck')
                ->visible(fn () => ($this->record->qty_out - $this->record->qty_in) == 0 && $this->record->qty_available > 0)
                ->form([
                    \Filament\Forms\Components\Select::make('to_branch_id')
                        ->label('Cabang Tujuan')
                        ->options(\App\Models\Branch::pluck('name', 'id'))
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->maxValue(fn() => $this->record->qty_available),
                    \Filament\Forms\Components\DatePicker::make('transaction_date')
                        ->label('Tanggal Pengiriman')
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Nomor Resi / Keterangan'),
                ])
                ->action(function (array $data) {
                    try {
                        app(\App\Services\BranchTransferService::class)->send($this->record, $data);
                        \Filament\Notifications\Notification::make()->success()->title('Aset dikirim ke cabang tujuan')->send();
                        $this->redirect(AssetResource::getUrl('index'));
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                    }
                }),

            \Filament\Actions\Action::make('servis_upgrade')
                ->label('Servis / Upgrade')
                ->color('warning')
                ->icon('heroicon-o-wrench-screwdriver')
                ->visible(fn () => !in_array($this->record->currentStatus?->code, ['in_transit', 'disposed']) && !\App\Models\AssetService::where('asset_id', $this->record->id)->where('status', 'open')->exists())
                ->form([
                    \Filament\Forms\Components\Select::make('service_kind_id')
                        ->label('Jenis Servis')
                        ->options(\App\Models\ServiceKind::pluck('name', 'id'))
                        ->required()
                        ->reactive(),
                    \Filament\Forms\Components\Radio::make('performed_by')
                        ->label('Dikerjakan Oleh')
                        ->options([
                            'internal' => 'Internal IT',
                            'vendor' => 'Vendor Eksternal',
                        ])
                        ->default('internal')
                        ->reactive(),
                    \Filament\Forms\Components\Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(\App\Models\Vendor::pluck('name', 'id'))
                        ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('performed_by') === 'vendor')
                        ->required(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('performed_by') === 'vendor'),
                    \Filament\Forms\Components\Toggle::make('item_left')
                        ->label('Barang Ditinggal?')
                        ->default(true),
                    \Filament\Forms\Components\DatePicker::make('started_at')
                        ->label('Tanggal Masuk')
                        ->default(now())
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('expected_at')
                        ->label('Estimasi Selesai'),
                    \Filament\Forms\Components\TextInput::make('ticket_ref')
                        ->label('No. Tiket / Referensi Vendor'),
                    \Filament\Forms\Components\Textarea::make('complaint')
                        ->label('Keluhan / Rencana Pekerjaan')
                        ->required(),
                    // Perubahan spesifikasi omitted for brevity in draft; can be handled with key-value or repeater if needed
                ])
                ->action(function (array $data) {
                    try {
                        app(\App\Services\ServiceService::class)->open($this->record, $data);
                        \Filament\Notifications\Notification::make()->success()->title('Catatan Servis Dibuka')->send();
                        $this->refreshFormData(['current_status_id']);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
