<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Barang')
                    ->description('Satu baris untuk satu jenis barang, dipakai berulang oleh setiap pembelian dan setiap unit.')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategori')
                            ->placeholder('Pilih Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('brand_id')
                            ->label('Merek')
                            ->placeholder('Pilih Merek')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')->required()->unique('brands', 'name'),
                            ]),
                        TextInput::make('model')
                            ->label('Tipe / Model')
                            ->placeholder('Contoh: ThinkPad T14 Gen 3')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Spesifikasi Bawaan')
                    ->description('Berlaku untuk semua unit barang ini, kecuali unit yang punya spesifikasi sendiri.')
                    ->schema([
                        KeyValue::make('specifications')
                            ->label('Spesifikasi')
                            ->keyLabel('Bagian')
                            ->valueLabel('Nilai'),
                        KeyValue::make('accessories')
                            ->label('Kelengkapan')
                            ->keyLabel('Item')
                            ->valueLabel('Keterangan'),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
