<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Vendor')
                    ->description('Vendor dipakai saat pembelian aset maupun servis.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Vendor')
                            ->placeholder('Contoh: PT Sumber Komputer')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Tipe')
                            ->placeholder('Pilih Tipe Vendor')
                            ->native(false)
                            ->options([
                                'toko' => 'Toko',
                                'servis' => 'Servis',
                                'keduanya' => 'Keduanya',
                            ])
                            ->required(),
                        TextInput::make('contact')
                            ->label('Nama Kontak')
                            ->placeholder('Contoh: Bpk. Andi'),
                        TextInput::make('phone')
                            ->label('Telepon')
                            ->placeholder('Contoh: 0812-3456-7890')
                            ->tel(),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->placeholder('Contoh: sales@vendor.co.id'),
                        TextInput::make('npwp')
                            ->label('NPWP')
                            ->placeholder('Contoh: 01.234.567.8-901.000'),
                        Select::make('payment_term_id')
                            ->label('Syarat Pembayaran')
                            ->placeholder('Pilih Syarat Pembayaran')
                            ->relationship('paymentTerm', 'name', fn ($query) => $query->where('is_active', true))
                            ->preload()
                            ->helperText('Dipakai sebagai bawaan saat membuat pesanan pembelian; masih bisa diubah per PO.')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan bila vendor tidak dipakai lagi.')
                            ->default(true)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Alamat & Catatan')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3),
                    ]),
            ]);
    }
}
