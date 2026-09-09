<?php

namespace App\Filament\Resources\AssetTransactions\Schemas;

use App\Filament\Support\AssetSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informasi Transaksi')
                    ->description('Riwayat transaksi dibuat otomatis oleh sistem. Ubah hanya bila melakukan koreksi data.')
                    ->columns(2)
                    ->schema([
                        AssetSelect::make('asset_id')
                            ->required(),
                        Select::make('type')
                            ->label('Jenis Transaksi')
                            ->placeholder('Pilih Jenis Transaksi')
                            ->native(false)
                            ->options([
                                'handover' => 'Serah Terima',
                                'return' => 'Pengembalian',
                                'status_change' => 'Perubahan Status',
                                'disposal' => 'Pemusnahan',
                                'branch_transfer' => 'Pindah Cabang',
                                'cancellation' => 'Pembatalan',
                                'correction' => 'Koreksi',
                                'legacy' => 'Data Lama',
                            ])
                            ->required(),
                        DatePicker::make('transaction_date')
                            ->label('Tanggal Transaksi')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->required(),
                    ]),

                Section::make('Pihak Terkait')
                    ->columns(2)
                    ->schema([
                        Select::make('from_employee_id')
                            ->label('Dari Karyawan')
                            ->placeholder('Pilih Karyawan')
                            ->relationship('fromEmployee', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('to_employee_id')
                            ->label('Ke Karyawan')
                            ->placeholder('Pilih Karyawan')
                            ->relationship('toEmployee', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('user_employee_id')
                            ->label('Pemakai')
                            ->placeholder('Pilih Pemakai')
                            ->relationship('userEmployee', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('handover_document_id')
                            ->label('Dokumen Serah Terima')
                            ->placeholder('Pilih Surat')
                            ->relationship('handoverDocument', 'document_number')
                            // Surat berstatus draf belum punya nomor; tanpa label
                            // pengganti, Filament menolak pilihan bernilai null.
                            ->getOptionLabelFromRecordUsing(fn ($record): string => filled($record->document_number)
                                ? $record->document_number
                                : 'DRAF #'.$record->id.' ('.$record->document_date?->translatedFormat('d M Y').')')
                            ->searchable()
                            ->preload(),
                        Select::make('from_branch_id')
                            ->label('Dari Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('fromBranch', 'name')
                            ->native(false)
                            ->preload(),
                        Select::make('to_branch_id')
                            ->label('Ke Cabang')
                            ->placeholder('Pilih Cabang')
                            ->relationship('toBranch', 'name')
                            ->native(false)
                            ->preload(),
                    ]),

                Section::make('Status & Dampak Stok')
                    ->columns(2)
                    ->schema([
                        Select::make('status_id')
                            ->label('Status Aset')
                            ->placeholder('Pilih Status')
                            ->relationship('status', 'name')
                            ->native(false)
                            ->preload()
                            ->required(),
                        Select::make('stock_direction')
                            ->label('Arah Stok')
                            ->placeholder('Pilih Arah Stok')
                            ->native(false)
                            ->options([
                                'out' => 'Keluar',
                                'in' => 'Masuk',
                                'neutral' => 'Netral',
                                'writeoff' => 'Write-off',
                            ])
                            ->required(),
                        Select::make('condition_after_id')
                            ->label('Kondisi Setelah')
                            ->placeholder('Pilih Kondisi')
                            ->relationship('conditionAfter', 'name')
                            ->native(false)
                            ->preload(),
                        Select::make('disposal_reason_id')
                            ->label('Alasan Pelepasan')
                            ->placeholder('Pilih Alasan')
                            ->relationship('disposalReason', 'name')
                            ->native(false)
                            ->preload(),
                    ]),

                Section::make('Catatan & Referensi')
                    ->columns(2)
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('service_id')
                            ->label('ID Servis Terkait')
                            ->numeric(),
                        TextInput::make('import_batch_id')
                            ->label('ID Batch Impor')
                            ->numeric(),
                        Select::make('created_by')
                            ->label('Dibuat Oleh')
                            ->placeholder('Pilih Pengguna')
                            ->relationship('createdBy', 'name')
                            ->searchable()
                            ->preload(),
                        Toggle::make('legacy')
                            ->label('Data Lama (Migrasi)')
                            ->helperText('Menandai transaksi hasil impor data lama.')
                            ->required(),
                    ]),
            ]);
    }
}
