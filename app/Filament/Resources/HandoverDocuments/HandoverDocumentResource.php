<?php

namespace App\Filament\Resources\HandoverDocuments;

use App\Filament\Resources\HandoverDocuments\Pages\CreateHandoverDocument;
use App\Filament\Resources\HandoverDocuments\Pages\EditHandoverDocument;
use App\Filament\Resources\HandoverDocuments\Pages\ListHandoverDocuments;
use App\Filament\Resources\HandoverDocuments\Schemas\HandoverDocumentForm;
use App\Filament\Resources\HandoverDocuments\Tables\HandoverDocumentsTable;
use App\Models\HandoverDocument;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class HandoverDocumentResource extends Resource
{
    protected static ?string $model = HandoverDocument::class;

    protected static ?string $modelLabel = 'Surat Serah Terima';

    protected static ?string $pluralModelLabel = 'Surat Serah Terima';

    protected static \UnitEnum|string|null $navigationGroup = 'Dokumen';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function form(Schema $schema): Schema
    {
        return HandoverDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HandoverDocumentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHandoverDocuments::route('/'),
            'create' => CreateHandoverDocument::route('/create'),
            'edit' => EditHandoverDocument::route('/{record}/edit'),
        ];
    }
}
