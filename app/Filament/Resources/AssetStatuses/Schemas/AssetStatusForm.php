<?php

namespace App\Filament\Resources\AssetStatuses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetStatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Status')
                    ->description('Status menandai posisi aset saat ini, misalnya terdaftar, dipakai, atau servis.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('Contoh: in_use')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nama Status')
                            ->placeholder('Contoh: Dipakai')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Perilaku Stok')
                    ->description('Menentukan bagaimana status ini memengaruhi perhitungan stok aset.')
                    ->columns(2)
                    ->schema([
                        Select::make('stock_direction')
                            ->label('Arah Stok')
                            ->native(false)
                            ->options([
                                'out' => 'Keluar',
                                'in' => 'Masuk',
                                'neutral' => 'Netral',
                                'writeoff' => 'Write-off',
                            ])
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('transferable')
                            ->label('Dapat Dipindahkan')
                            ->helperText('Aset berstatus ini boleh diserahkan atau dipindah cabang.')
                            ->required(),
                        Toggle::make('requires_qty')
                            ->label('Butuh Jumlah')
                            ->helperText('Transaksi dengan status ini wajib mengisi jumlah unit.')
                            ->required(),
                        Toggle::make('clears_holder')
                            ->label('Mengosongkan Pemegang')
                            ->helperText('Pemegang aset dikosongkan saat status ini dipakai.')
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
