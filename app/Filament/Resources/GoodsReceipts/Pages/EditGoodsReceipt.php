<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Services\GoodsReceiptService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('terima')
                ->label('Setujui Penerimaan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalDescription(fn (): string => trim(
                    'Unit aset akan dibuat sebanyak baris pada penerimaan ini. '
                    .($this->record->peringatanSelisihHarga() ?? '')
                ))
                ->action(function () {
                    try {
                        $selisih = $this->record->peringatanSelisihHarga();

                        $gr = app(GoodsReceiptService::class)->receive($this->record);

                        Notification::make()
                            ->success()
                            ->title('Penerimaan disetujui')
                            ->body(trim($gr->items()->count().' unit dibuat dengan nomor '.$gr->gr_number.'. '.($selisih ?? '')))
                            ->when($selisih !== null, fn (Notification $notifikasi) => $notifikasi->persistent())
                            ->send();

                        $this->redirect(GoodsReceiptResource::getUrl('index'));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Gagal')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            DeleteAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
