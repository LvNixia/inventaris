<?php

namespace App\Filament\Resources\HandoverDocuments\Schemas;

use Illuminate\Database\Eloquent\Builder;
use App\Filament\Support\AssetSelect;
use App\Models\Asset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;

class HandoverDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(12)
                    ->schema([
                        Section::make('Informasi Surat')
                            ->description('Identitas surat dan pihak yang menandatangani.')
                            ->columnSpan(5)
                            ->columns(2)
                            ->schema([
                                Select::make('branch_id')
                                    ->label('Cabang')
                                    ->placeholder('Pilih Cabang')
                                    ->relationship('branch', 'name')
                                    ->preload()
                                    ->default(fn () => auth()->user()->branch_id)
                                    ->disabled(fn () => auth()->user()->role !== \App\Enums\Role::AdminPusat)
                                    // Field terkunci tetap ikut tersimpan; tanpa ini branch_id kosong bagi admin cabang.
                                    ->dehydrated()
                                    ->required(),
                                DatePicker::make('document_date')
                                    ->label('Tanggal Surat')
                                    ->native(false)
                                    ->displayFormat('d M Y')
                                    ->default(now())
                                    ->required()
                                    ->maxDate(now()),
                                Select::make('first_party_id')
                                    ->label('Pihak Pertama')
                                    ->helperText('Yang menyerahkan')
                                    ->placeholder('Pilih karyawan')
                                    ->relationship('firstParty', 'name')
                                    ->default(fn () => auth()->user()->employee_id)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('second_party_id')
                                    ->label('Pihak Kedua')
                                    ->helperText('Yang menerima')
                                    ->placeholder('Pilih karyawan')
                                    ->relationship('secondParty', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->different('first_party_id'),
                                Select::make('witness_id')
                                    ->label('Mengetahui (Saksi)')
                                    ->placeholder('Pilih karyawan yang berhak menjadi saksi')
                                    ->relationship('witness', 'name', fn ($query) => $query->where('can_sign_as_witness', true))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull(),
                                TextInput::make('document_number')
                                    ->label('Nomor Surat')
                                    ->disabled()
                                    ->placeholder('Dibuat otomatis'),
                                TextInput::make('status')
                                    ->label('Status')
                                    ->disabled()
                                    ->default('draft')
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'draft' => 'DRAF',
                                        'issued' => 'TERBIT',
                                        'cancelled' => 'DIBATALKAN',
                                        default => strtoupper($state),
                                    }),
                            ]),

                        Section::make('Daftar Barang')
                            ->description('Satu baris untuk satu barang yang diserahkan.')
                            ->columnSpan(7)
                            ->schema([
                                Repeater::make('items')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->table([
                                        TableColumn::make('Barang')
                                            ->width('38%')
                                            ->verticalAlignment(VerticalAlignment::Center)
                                            ->markAsRequired(),
                                        TableColumn::make('Jumlah')
                                            ->width('12%')
                                            ->alignment(Alignment::Center)
                                            ->verticalAlignment(VerticalAlignment::Center)
                                            ->markAsRequired(),
                                        TableColumn::make('Pemakai')
                                            ->width('25%')
                                            ->verticalAlignment(VerticalAlignment::Center),
                                        TableColumn::make('Keterangan')
                                            ->width('25%')
                                            ->verticalAlignment(VerticalAlignment::Center),
                                    ])
                                    ->schema([
                                        AssetSelect::make(
                                            // Hanya aset yang masih bersisa stok dan statusnya
                                            // boleh dipindahtangankan.
                                            modifyQueryUsing: fn (Builder $query) => $query
                                                ->where('qty_available', '>', 0)
                                                ->whereHas('currentStatus', fn (Builder $status) => $status->where('transferable', true)),
                                            // Stok ikut ditampilkan karena satu baris serah terima
                                            // bisa mengambil beberapa unit sekaligus.
                                            labelUsing: fn (Asset $asset): string => $asset->display_name . ' (Stok: ' . $asset->qty_available . ')',
                                        )
                                            ->hiddenLabel()
                                            ->required()
                                            ->live()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                        TextInput::make('quantity')
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->maxValue(function (Get $get) {
                                                $asset = Asset::find($get('asset_id'));
                                                return $asset ? $asset->qty_available : 1;
                                            }),
                                        Select::make('user_employee_id')
                                            ->hiddenLabel()
                                            ->placeholder('Pilih Pemakai')
                                            ->relationship('userEmployee', 'name')
                                            ->searchable(),
                                        Textarea::make('remarks')
                                            ->hiddenLabel()
                                            ->rows(1),
                                    ])
                                    ->addActionLabel('Tambah Barang')
                                    ->minItems(1)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
