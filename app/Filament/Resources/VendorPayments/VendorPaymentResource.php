<?php

namespace App\Filament\Resources\VendorPayments;

use App\Filament\Resources\VendorPayments\Pages\CreateVendorPayment;
use App\Filament\Resources\VendorPayments\Pages\ListVendorPayments;
use App\Filament\Resources\VendorPayments\Schemas\VendorPaymentForm;
use App\Filament\Resources\VendorPayments\Tables\VendorPaymentsTable;
use App\Models\VendorPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class VendorPaymentResource extends Resource
{
    protected static ?string $model = VendorPayment::class;

    protected static ?string $modelLabel = 'Pembayaran Vendor';

    protected static ?string $pluralModelLabel = 'Pembayaran Vendor';

    protected static \UnitEnum|string|null $navigationGroup = 'Pengadaan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return VendorPaymentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VendorPaymentsTable::configure($table);
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
            'index' => ListVendorPayments::route('/'),
            'create' => CreateVendorPayment::route('/create'),
        ];
    }
}
