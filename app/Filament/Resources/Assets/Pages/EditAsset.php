<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\AssetService as AssetServiceModel;
use App\Models\AssetStatus;
use App\Models\Branch;
use App\Models\Condition;
use App\Models\DisposalReason;
use App\Models\ServiceKind;
use App\Models\Vendor;
use App\Services\BranchTransferService;
use App\Services\ServiceService;
use App\Services\TransactionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Semua aksi di sini menyangkut satu unit, jadi tidak ada lagi isian jumlah.
 */
class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),

            Action::make('tarik_kembali')
                ->label('Tarik Kembali')
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $this->record->isHeld())
                ->modalDescription(fn () => 'Unit ini dipegang '.$this->record->currentHolder?->name.'.')
                ->form([
                    DatePicker::make('transaction_date')
                        ->label('Tanggal Penarikan')
                        ->default(now())
                        ->required(),
                    Select::make('condition_after_id')
                        ->label('Kondisi Saat Diterima')
                        ->options(Condition::pluck('name', 'id'))
                        ->default(fn () => $this->record->condition_id),
                    Textarea::make('notes')
                        ->label('Catatan'),
                ])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(TransactionService::class)->return($this->record, $data),
                    'Unit ditarik kembali',
                )),

            Action::make('ubah_status')
                ->label('Ubah Status')
                ->color('info')
                ->icon('heroicon-o-tag')
                ->visible(fn () => $this->record->retired_at === null)
                ->form([
                    Select::make('status_id')
                        ->label('Status Baru')
                        ->options(function () {
                            $kode = $this->record->current_holder_id
                                ? ['service', 'broken', 'registered', 'in_use']
                                : ['service', 'broken', 'registered', 'spare'];

                            return AssetStatus::whereIn('code', $kode)->pluck('name', 'id');
                        })
                        ->required(),
                    Select::make('condition_after_id')
                        ->label('Kondisi Baru (Opsional)')
                        ->options(Condition::pluck('name', 'id')),
                    Textarea::make('notes')
                        ->label('Catatan'),
                ])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(TransactionService::class)->changeStatus($this->record, $data),
                    'Status diubah',
                )),

            Action::make('dilepas')
                ->label('Dilepas / Dijual')
                ->color('danger')
                ->icon('heroicon-o-archive-box-x-mark')
                ->visible(fn () => $this->record->retired_at === null)
                ->form([
                    DatePicker::make('transaction_date')
                        ->label('Tanggal Pelepasan')
                        ->default(now())
                        ->required(),
                    Select::make('disposal_reason_id')
                        ->label('Alasan')
                        ->options(DisposalReason::pluck('name', 'id'))
                        ->required(),
                    Textarea::make('notes')
                        ->label('Catatan Tambahan (misalnya harga jual)'),
                ])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(TransactionService::class)->dispose($this->record, $data),
                    'Unit dilepas',
                )),

            Action::make('pindah_cabang')
                ->label('Pindah Cabang')
                ->color('primary')
                ->icon('heroicon-o-truck')
                ->visible(fn () => $this->record->isAvailable())
                ->form([
                    Select::make('to_branch_id')
                        ->label('Cabang Tujuan')
                        ->options(fn () => Branch::where('id', '!=', $this->record->branch_id)->pluck('name', 'id'))
                        ->required(),
                    DatePicker::make('transaction_date')
                        ->label('Tanggal Pengiriman')
                        ->default(now())
                        ->required(),
                    Textarea::make('notes')
                        ->label('Nomor Resi / Keterangan'),
                ])
                ->action(function (array $data) {
                    try {
                        app(BranchTransferService::class)->send($this->record, $data);
                        Notification::make()->success()->title('Unit dikirim ke cabang tujuan')->send();
                        $this->redirect(AssetResource::getUrl('index'));
                    } catch (\Exception $e) {
                        Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                    }
                }),

            Action::make('servis_upgrade')
                ->label('Servis / Upgrade')
                ->color('warning')
                ->icon('heroicon-o-wrench-screwdriver')
                ->visible(fn () => ! in_array($this->record->currentStatus?->code, ['in_transit', 'disposed'])
                    && ! AssetServiceModel::where('asset_id', $this->record->id)->where('status', 'open')->exists())
                ->form([
                    Select::make('service_kind_id')
                        ->label('Jenis Servis')
                        ->options(ServiceKind::pluck('name', 'id'))
                        ->required(),
                    Radio::make('performed_by')
                        ->label('Dikerjakan Oleh')
                        ->options(['internal' => 'Internal IT', 'vendor' => 'Vendor Eksternal'])
                        ->default('internal')
                        ->live(),
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(Vendor::pluck('name', 'id'))
                        ->visible(fn (Get $get) => $get('performed_by') === 'vendor')
                        ->required(fn (Get $get) => $get('performed_by') === 'vendor'),
                    Toggle::make('item_left')
                        ->label('Barang Ditinggal?')
                        ->helperText('Bila ditinggal, nomor seri unit ini harus sudah terisi.')
                        ->default(true),
                    DatePicker::make('started_at')
                        ->label('Tanggal Masuk')
                        ->default(now())
                        ->required(),
                    DatePicker::make('expected_at')
                        ->label('Estimasi Selesai'),
                    TextInput::make('ticket_ref')
                        ->label('No. Tiket / Referensi Vendor'),
                    Textarea::make('complaint')
                        ->label('Keluhan / Rencana Pekerjaan')
                        ->required(),
                ])
                ->action(fn (array $data) => $this->jalankan(
                    fn () => app(ServiceService::class)->open($this->record, $data),
                    'Catatan servis dibuka',
                )),
        ];
    }

    /**
     * Menjalankan satu aksi lalu menyegarkan kolom yang bisa ikut berubah.
     */
    protected function jalankan(callable $aksi, string $judulSukses): void
    {
        try {
            $aksi();

            Notification::make()->success()->title($judulSukses)->send();

            $this->refreshFormData(['current_status_id', 'current_holder_id', 'condition_id', 'retired_at']);
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
        }
    }
}
