<?php

namespace App\Filament\Resources\AssetServices\Schemas;

use App\Filament\Support\AssetSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class AssetServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Servis')
                    ->description('Data awal saat aset masuk servis atau upgrade.')
                    ->columns(2)
                    ->schema([
                        AssetSelect::make('asset_id')
                            ->required(),
                        Select::make('service_kind_id')
                            ->label('Jenis Servis')
                            ->placeholder('Pilih Jenis Servis')
                            ->relationship('serviceKind', 'name')
                            ->native(false)
                            ->preload()
                            ->required(),
                        Select::make('performed_by')
                            ->label('Dikerjakan Oleh')
                            ->placeholder('Pilih Pelaksana')
                            ->native(false)
                            ->options(['internal' => 'Internal IT', 'vendor' => 'Vendor Eksternal'])
                            ->required()
                            ->live(),
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->placeholder('Pilih Vendor')
                            ->relationship('vendor', 'name')
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get) => $get('performed_by') === 'vendor'),
                        Toggle::make('item_left')
                            ->label('Barang Ditinggal?')
                            ->required(),
                        TextInput::make('ticket_ref')
                            ->label('Referensi Tiket / No. Tiket Vendor'),
                    ]),

                Section::make('Jadwal & Waktu')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('started_at')
                            ->label('Tanggal Masuk')
                            ->native(false)
                            ->displayFormat('d M Y H:i')
                            ->required(),
                        DateTimePicker::make('expected_at')
                            ->label('Estimasi Selesai')
                            ->native(false)
                            ->displayFormat('d M Y H:i'),
                        DateTimePicker::make('finished_at')
                            ->label('Tanggal Selesai')
                            ->native(false)
                            ->displayFormat('d M Y H:i'),
                    ]),

                Section::make('Detail Pekerjaan')
                    ->columns(2)
                    ->schema([
                        Textarea::make('complaint')
                            ->label('Keluhan')
                            ->placeholder('Keluhan pengguna / rencana pekerjaan')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('work_done')
                            ->label('Pekerjaan yang Dilakukan')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('spec_before')
                            ->label('Spesifikasi Sebelum'),
                        TextInput::make('spec_after')
                            ->label('Spesifikasi Sesudah'),
                        Select::make('condition_after_id')
                            ->label('Kondisi Setelah Servis')
                            ->placeholder('Pilih Kondisi')
                            ->relationship('conditionAfter', 'name')
                            ->native(false)
                            ->preload(),
                        Select::make('service_result_id')
                            ->label('Hasil Servis')
                            ->placeholder('Pilih Hasil Servis')
                            ->relationship('serviceResult', 'name')
                            ->native(false)
                            ->preload(),
                    ]),

                Section::make('Biaya & Garansi')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cost_service')
                            ->label('Biaya Servis')
                            ->numeric()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters(['.', ',']),
                        TextInput::make('cost_parts')
                            ->label('Biaya Suku Cadang')
                            ->numeric()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                            ->stripCharacters(['.', ',']),
                        DatePicker::make('service_warranty_until')
                            ->label('Garansi Servis Sampai')
                            ->native(false)
                            ->displayFormat('d M Y'),
                    ]),

                Section::make('Status & Penanggung Jawab')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->native(false)
                            ->options(['open' => 'Terbuka', 'closed' => 'Selesai'])
                            ->default('open')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('opened_by')
                            ->label('Dibuka Oleh')
                            ->placeholder('Pilih Pengguna')
                            ->relationship('openedBy', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('closed_by')
                            ->label('Ditutup Oleh')
                            ->placeholder('Pilih Pengguna')
                            ->relationship('closedBy', 'name')
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
