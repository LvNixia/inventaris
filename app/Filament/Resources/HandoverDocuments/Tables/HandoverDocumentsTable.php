<?php

namespace App\Filament\Resources\HandoverDocuments\Tables;

use App\Enums\Role;
use App\Services\HandoverCancellation;
use App\Services\HandoverService;
use App\Services\PdfRenderer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class HandoverDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->sortable()
                    ->default('DRAF')
                    ->color(fn ($record) => $record->status === 'draft' ? 'gray' : 'primary'),
                TextColumn::make('document_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
                TextColumn::make('firstParty.name')
                    ->label('Diserahkan Oleh')
                    ->searchable(),
                TextColumn::make('secondParty.name')
                    ->label('Diterima Oleh')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draf',
                        'issued' => 'Terbit',
                        'cancelled' => 'Dibatalkan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'issued' => 'success',
                        'cancelled' => 'danger',
                    }),
                TextColumn::make('items_count')
                    ->label('Jml Barang')
                    ->counts('items'),
            ])
            ->defaultSort('document_date', 'desc')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
                SelectFilter::make('status')
                    ->options(['draft' => 'Draf', 'issued' => 'Terbit', 'cancelled' => 'Dibatalkan']),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft'),
                Action::make('terbitkan')
                    ->label('Terbitkan')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(HandoverService::class)->issue($record);
                            // Generate PDF immediately or via queued job?
                            // For now, render sync
                            $path = app(PdfRenderer::class)->handover($record);
                            $record->pdf_path = $path;
                            $record->save();

                            Notification::make()->success()->title('Surat diterbitkan!')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('unduh_pdf')
                    ->label('Unduh PDF')
                    ->color('primary')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn ($record) => $record->status !== 'draft' && $record->pdf_path)
                    ->action(function ($record) {
                        if (! $record->pdf_path || ! Storage::exists($record->pdf_path)) {
                            Notification::make()->danger()->title('PDF tidak ditemukan')->send();

                            return;
                        }
                        $filename = str_replace('/', '_', $record->document_number).'.pdf';

                        return response()->download(storage_path('app/'.$record->pdf_path), $filename);
                    }),
                Action::make('preview_draft')
                    ->label('Pratinjau Draf')
                    ->color('gray')
                    ->icon('heroicon-o-eye')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $pdf = app(PdfRenderer::class)->handover($record, true);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf;
                        }, 'draft.pdf', ['Content-Type' => 'application/pdf']);
                    }),
                Action::make('batalkan')
                    ->label('Batalkan')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn ($record) => $record->status === 'issued' && auth()->user()->role === Role::AdminPusat)
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Pembatalan')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(HandoverCancellation::class)->cancel($record, $data['reason']);
                            Notification::make()->success()->title('Surat dibatalkan!')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal Membatalkan')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                // Handover documents shouldn't be bulk deleted if issued.
            ]);
    }
}
