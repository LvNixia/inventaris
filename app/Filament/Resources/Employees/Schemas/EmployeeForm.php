<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data Karyawan')
                    ->description('Karyawan menjadi pemegang aset pada surat serah terima.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Karyawan')
                            ->placeholder('Nama lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nik')
                            ->label('NIK')
                            ->placeholder('Nomor induk karyawan')
                            ->maxLength(50),
                        Select::make('position_id')
                            ->label('Jabatan')
                            ->placeholder('Pilih Jabatan')
                            ->relationship('position', 'name')
                            ->native(false)
                            ->searchable()
                            ->preload(),
                        Select::make('division_id')
                            ->label('Divisi')
                            ->placeholder('Pilih Divisi')
                            ->relationship('division', 'name')
                            ->native(false)
                            ->searchable()
                            ->preload(),
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()->branch_id)
                            ->disabled(fn () => auth()->user()->role !== Role::AdminPusat)
                            ->dehydrated()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Status Karyawan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan saat karyawan keluar; aset wajib ditarik lebih dulu.')
                            ->default(true)
                            ->required(),
                        Toggle::make('can_sign_as_witness')
                            ->label('Bisa Jadi Saksi')
                            ->helperText('Karyawan ini bisa dipilih sebagai saksi pada surat serah terima.')
                            ->required(),
                    ]),
            ]);
    }
}
