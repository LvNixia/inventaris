<?php

namespace App\Filament\Resources\Conditions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConditionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Kondisi')
                    ->description('Kondisi dipakai saat pendataan, penyerahan, dan penarikan aset.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('Contoh: baik')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nama Kondisi')
                            ->placeholder('Contoh: Baik')
                            ->required()
                            ->maxLength(255),
                        Select::make('applies_to')
                            ->label('Berlaku Untuk')
                            ->native(false)
                            ->options([
                                'hardware' => 'Hardware',
                                'license' => 'Lisensi',
                                'all' => 'Semua',
                            ])
                            ->default('all')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->required(),
                        Toggle::make('is_system')
                            ->label('Bawaan Sistem')
                            ->helperText('Data bawaan sistem sebaiknya tidak diubah.')
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
