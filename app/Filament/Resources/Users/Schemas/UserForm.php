<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Akun Pengguna')
                    ->description('Data masuk pengguna ke aplikasi.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->placeholder('Nama lengkap pengguna')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->placeholder('nama@perusahaan.co.id')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Kosongkan bila tidak ingin mengubah kata sandi.')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Peran & Penempatan')
                    ->description('Menentukan hak akses dan cakupan data yang bisa dilihat pengguna.')
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->label('Peran')
                            ->placeholder('Pilih Peran')
                            ->native(false)
                            ->options(Role::class)
                            ->default('viewer')
                            ->required(),
                        Select::make('branch_id')
                            ->label('Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('branch', 'name')
                            ->native(false)
                            ->searchable()
                            ->preload(),
                        Select::make('employee_id')
                            ->label('Karyawan Terkait')
                            ->placeholder('Pilih Karyawan')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Dipakai sebagai penanda tangan pada surat serah terima.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan untuk mencabut akses masuk.')
                            ->default(true)
                            ->required(),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email Diverifikasi Pada')
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
