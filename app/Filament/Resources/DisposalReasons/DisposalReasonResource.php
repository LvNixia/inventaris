<?php

namespace App\Filament\Resources\DisposalReasons;

use App\Filament\Resources\DisposalReasons\Pages\CreateDisposalReason;
use App\Filament\Resources\DisposalReasons\Pages\EditDisposalReason;
use App\Filament\Resources\DisposalReasons\Pages\ListDisposalReasons;
use App\Filament\Resources\DisposalReasons\Schemas\DisposalReasonForm;
use App\Filament\Resources\DisposalReasons\Tables\DisposalReasonsTable;
use App\Models\DisposalReason;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DisposalReasonResource extends Resource
{
    protected static ?string $model = DisposalReason::class;

    protected static ?string $modelLabel = 'Alasan Pelepasan';
    protected static ?string $pluralModelLabel = 'Alasan Pelepasan';
    protected static \UnitEnum|string|null $navigationGroup = 'Referensi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-x-mark';
    protected static ?int $navigationSort = 7;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DisposalReasonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisposalReasonsTable::configure($table);
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
            'index' => ListDisposalReasons::route('/'),
            'create' => CreateDisposalReason::route('/create'),
            'edit' => EditDisposalReason::route('/{record}/edit'),
        ];
    }
}
