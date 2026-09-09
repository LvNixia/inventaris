<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Models\AssetAttachment;
use App\Models\AssetService;
use App\Models\AttachmentType;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'Lampiran';

    protected static ?string $modelLabel = 'Lampiran';

    protected static ?string $pluralModelLabel = 'Lampiran';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-paper-clip';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('attachment_type_id')
                    ->label('Jenis Lampiran')
                    ->options(fn () => AttachmentType::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('service_id')
                    ->label('Terkait Servis (opsional)')
                    ->helperText('Isi bila berkas ini milik satu catatan servis, misalnya tanda terima servis.')
                    ->options(fn () => AssetService::where('asset_id', $this->getOwnerRecord()->getKey())
                        ->with('serviceKind')
                        ->get()
                        ->mapWithKeys(fn (AssetService $service) => [
                            $service->id => trim(sprintf(
                                '#%d %s - %s',
                                $service->id,
                                $service->serviceKind?->name ?? 'Servis',
                                $service->started_at?->format('d/m/Y') ?? '-',
                            )),
                        ]))
                    ->searchable(),

                FileUpload::make('path')
                    ->label('Berkas')
                    ->directory('asset-attachments')
                    ->storeFileNamesIn('original_name')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ])
                    ->maxSize(10240)
                    ->helperText('JPG, PNG, WEBP, atau PDF. Maksimal 10 MB.')
                    ->downloadable()
                    ->openable()
                    ->required()
                    ->hiddenOn('edit'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('attachmentType.name')
                    ->label('Jenis')
                    ->badge()
                    ->sortable(),
                TextColumn::make('original_name')
                    ->label('Nama Berkas')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('service_id')
                    ->label('Servis')
                    ->formatStateUsing(fn (?int $state): string => $state ? "#{$state}" : '-')
                    ->toggleable(),
                TextColumn::make('size')
                    ->label('Ukuran')
                    ->formatStateUsing(fn (?int $state): string => $state ? number_format($state / 1024, 0, ',', '.').' KB' : '-')
                    ->toggleable(),
                TextColumn::make('uploader.name')
                    ->label('Diunggah Oleh')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('attachment_type_id')
                    ->label('Jenis Lampiran')
                    ->options(fn () => AttachmentType::pluck('name', 'id')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah Lampiran')
                    ->mutateDataUsing(fn (array $data): array => $this->fillFileMetadata($data)),
            ])
            ->recordActions([
                Action::make('unduh')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (AssetAttachment $record) => Storage::disk(static::diskName())
                        ->download($record->path, $record->original_name)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Lengkapi metadata berkas yang tidak dikirim oleh FileUpload.
     */
    protected function fillFileMetadata(array $data): array
    {
        $disk = Storage::disk(static::diskName());
        $path = $data['path'] ?? null;

        if ($path && $disk->exists($path)) {
            $data['mime'] = $disk->mimeType($path) ?: 'application/octet-stream';
            $data['size'] = $disk->size($path);
        }

        $data['original_name'] ??= $path ? basename($path) : '';
        $data['uploaded_by'] = auth()->id();

        return $data;
    }

    protected static function diskName(): string
    {
        return config('filesystems.default');
    }
}
