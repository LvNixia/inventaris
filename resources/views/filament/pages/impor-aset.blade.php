<x-filament-panels::page>
    {{--
        Halaman ini memakai CSS sendiri, bukan kelas utilitas Tailwind, dengan
        alasan yang sama seperti halaman Panduan: aplikasi tidak mengompilasi
        Tailwind, sehingga hanya kelas komponen .fi-* milik Filament yang ada.
    --}}
    @push('styles')
        <style>
            .impor { display: flex; flex-direction: column; row-gap: var(--app-section-gap, 1.25rem); }
            .impor p { margin: 0; font-size: 0.875rem; color: var(--gray-600); }
            .impor p + p { margin-top: 0.75rem; }
            .impor strong { color: var(--gray-950); font-weight: 600; }

            .impor-angka {
                display: grid;
                gap: 0.75rem;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            @media (min-width: 48rem) {
                .impor-angka { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            }

            .impor-angka-item {
                background: var(--gray-50);
                border-radius: var(--radius-lg, 0.5rem);
                box-shadow: inset 0 0 0 1px var(--gray-200);
                padding: 0.875rem;
            }

            .impor-angka-nilai { font-size: 1.5rem; font-weight: 700; line-height: 1.2; color: var(--gray-950); }
            .impor-angka-label { font-size: 0.75rem; color: var(--gray-600); margin-top: 0.125rem; }

            .impor-tabel { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
            .impor-tabel th, .impor-tabel td {
                text-align: start;
                padding: 0.5rem 0.625rem;
                border-bottom: 1px solid var(--gray-200);
                vertical-align: top;
            }
            .impor-tabel th { font-weight: 600; color: var(--gray-950); white-space: nowrap; }
            .impor-tabel td { color: var(--gray-600); }
            .impor-gulir { overflow-x: auto; }

            .impor-kode {
                font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                font-size: 0.8125rem;
                background: var(--gray-100);
                border-radius: 0.25rem;
                padding: 0.0625rem 0.25rem;
            }

            .impor-galat { list-style: disc; padding-inline-start: 1.125rem; font-size: 0.8125rem; color: var(--danger-600, #dc2626); margin: 0; }
            .impor-galat li + li { margin-top: 0.375rem; }
        </style>
    @endpush

    <div class="impor">
        <x-filament::section>
            <x-slot name="heading">1. Unggah berkas</x-slot>
            <x-slot name="description">Berkas diperiksa lebih dulu; tidak ada data yang tertulis sebelum kamu menekan Jalankan Impor.</x-slot>

            {{ $this->form }}

            <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <x-filament::button wire:click="periksa" icon="heroicon-o-magnifying-glass" color="gray">
                    Periksa Berkas
                </x-filament::button>

                @if ($pratinjau && $pratinjau['ringkas']['sah'] > 0)
                    <x-filament::button
                        wire:click="jalankan"
                        icon="heroicon-o-arrow-up-tray"
                        wire:confirm="Impor {{ $pratinjau['ringkas']['sah'] }} unit sekarang?"
                    >
                        Jalankan Impor
                    </x-filament::button>
                @endif
            </div>
        </x-filament::section>

        @if ($pratinjau)
            <x-filament::section>
                <x-slot name="heading">2. Hasil pemeriksaan</x-slot>

                <div class="impor-angka">
                    <div class="impor-angka-item">
                        <div class="impor-angka-nilai">{{ $pratinjau['ringkas']['total'] }}</div>
                        <div class="impor-angka-label">Baris terbaca</div>
                    </div>
                    <div class="impor-angka-item">
                        <div class="impor-angka-nilai">{{ $pratinjau['ringkas']['sah'] }}</div>
                        <div class="impor-angka-label">Siap diimpor</div>
                    </div>
                    <div class="impor-angka-item">
                        <div class="impor-angka-nilai">{{ $pratinjau['ringkas']['gagal'] }}</div>
                        <div class="impor-angka-label">Bermasalah</div>
                    </div>
                    <div class="impor-angka-item">
                        <div class="impor-angka-nilai">{{ $pratinjau['ringkas']['barang_baru'] }}</div>
                        <div class="impor-angka-label">Barang baru dibuat</div>
                    </div>
                </div>

                @if ($pratinjau['ringkas']['gagal'] > 0)
                    <p style="margin-top: 1rem;"><strong>Baris bermasalah akan dilewati.</strong> Sisanya tetap masuk. Perbaiki berkasnya lalu unggah ulang bila ingin semuanya ikut.</p>

                    <ul class="impor-galat" style="margin-top: 0.75rem;">
                        @foreach (array_slice($pratinjau['galat'], 0, 25) as $galat)
                            <li>{{ $galat }}</li>
                        @endforeach
                    </ul>

                    @if (count($pratinjau['galat']) > 25)
                        <p style="margin-top: 0.75rem;">…dan {{ count($pratinjau['galat']) - 25 }} masalah lain.</p>
                    @endif
                @endif
            </x-filament::section>

            <x-filament::section collapsible collapsed>
                <x-slot name="heading">Pratinjau isi berkas</x-slot>
                <x-slot name="description">Sepuluh baris pertama.</x-slot>

                <div class="impor-gulir">
                    <table class="impor-tabel">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Kategori</th>
                                <th>Merek</th>
                                <th>Model</th>
                                <th>Nomor Seri</th>
                                <th>Cabang</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pratinjau['baris']->take(10) as $i => $baris)
                                <tr>
                                    <td>{{ $i + 2 }}</td>
                                    <td>{{ $baris['kategori'] }}</td>
                                    <td>{{ $baris['merek'] }}</td>
                                    <td>{{ $baris['model'] }}</td>
                                    <td>{{ $baris['serial_number'] }}</td>
                                    <td>{{ $baris['cabang'] }}</td>
                                    <td>
                                        @if ($baris['_galat'] === [])
                                            <x-filament::badge color="success" size="xs">Siap</x-filament::badge>
                                        @else
                                            <x-filament::badge color="danger" size="xs">{{ implode('; ', $baris['_galat']) }}</x-filament::badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Kolom yang dibaca</x-slot>
            <x-slot name="description">Judul kolom tidak boleh berubah. Urutannya bebas, kolom yang tidak dipakai boleh dihilangkan.</x-slot>

            <div class="impor-gulir">
                <table class="impor-tabel">
                    <thead>
                        <tr>
                            <th>Kolom</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->kolom as $nama => $keterangan)
                            <tr>
                                <td><span class="impor-kode">{{ $nama }}</span></td>
                                <td>{{ $keterangan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Riwayat impor</x-slot>
            <x-slot name="description">Impor bisa dibatalkan selama unit-unitnya belum dipakai.</x-slot>

            @if ($this->riwayat->isEmpty())
                <p>Belum ada impor.</p>
            @else
                <div class="impor-gulir">
                    <table class="impor-tabel">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Berkas</th>
                                <th>Oleh</th>
                                <th>Masuk</th>
                                <th>Dilewati</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->riwayat as $batch)
                                <tr>
                                    <td>{{ $batch->created_at?->translatedFormat('d M Y H:i') }}</td>
                                    <td>{{ $batch->file_name }}</td>
                                    <td>{{ $batch->user?->name ?? '—' }}</td>
                                    <td>{{ $batch->created_count }}</td>
                                    <td>{{ $batch->failed_count }}</td>
                                    <td>
                                        <x-filament::badge
                                            size="xs"
                                            :color="match ($batch->status) {
                                                'done' => 'success',
                                                'reverted' => 'gray',
                                                'failed' => 'danger',
                                                default => 'warning',
                                            }"
                                        >
                                            {{ match ($batch->status) {
                                                'done' => 'Berhasil',
                                                'reverted' => 'Dibatalkan',
                                                'failed' => 'Gagal',
                                                default => 'Diproses',
                                            } }}
                                        </x-filament::badge>
                                    </td>
                                    <td>
                                        @if ($batch->canBeReverted())
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                wire:click="batalkan({{ $batch->id }})"
                                                wire:confirm="Hapus {{ $batch->created_count }} unit dari impor ini?"
                                            >
                                                Batalkan
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
