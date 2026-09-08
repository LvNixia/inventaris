<x-filament-panels::page>
    {{--
        Memakai CSS sendiri, bukan kelas utilitas Tailwind: aplikasi tidak
        mengompilasi Tailwind dan berkas CSS Filament hanya memuat kelas
        komponen .fi-*, sehingga kelas utilitas tidak berpengaruh.
        Warna mengambil variabel tema Filament agar ikut mode gelap.
    --}}
    @push('styles')
        <style>
            .cek-baris { display: flex; align-items: flex-start; gap: 0.75rem; }

            .cek-ikon { flex: none; width: 1.75rem; height: 1.75rem; }
            .cek-ikon-sukses { color: var(--success-600, #1f6b4a); }
            .cek-ikon-peringatan { color: var(--warning-600, #8a5a1e); }
            .cek-ikon-info { width: 1.25rem; height: 1.25rem; color: var(--gray-400); }

            .cek-judul { font-size: 0.9375rem; font-weight: 600; color: var(--gray-950); margin: 0 0 0.25rem; }
            .cek-teks { font-size: 0.875rem; color: var(--gray-500); margin: 0; }

            .cek-tabel { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
            .cek-tabel th {
                text-align: start;
                font-weight: 500;
                color: var(--gray-500);
                padding: 0.375rem 1rem 0.375rem 0;
                border-bottom: 1px solid var(--gray-200);
            }
            .cek-tabel td {
                padding: 0.375rem 1rem 0.375rem 0;
                vertical-align: top;
                border-bottom: 1px solid var(--gray-200);
                color: var(--gray-600);
            }
            .cek-tabel td.cek-ref { font-weight: 500; color: var(--gray-950); width: 14rem; }
            .cek-gulir { overflow-x: auto; }

            :is(.dark, .dark *) .cek-judul,
            :is(.dark, .dark *) .cek-tabel td.cek-ref { color: #fff; }
            :is(.dark, .dark *) .cek-teks { color: var(--gray-400); }
            :is(.dark, .dark *) .cek-tabel td { color: var(--gray-300); }
            :is(.dark, .dark *) .cek-tabel th,
            :is(.dark, .dark *) .cek-tabel td { border-color: rgba(255, 255, 255, 0.1); }
        </style>
    @endpush

    @if (! $hasRun)
        <x-filament::section>
            <x-slot name="heading">Belum ada pemeriksaan dijalankan</x-slot>
            <x-slot name="description">
                Pemeriksaan menelusuri seluruh data aset dan transaksi, sehingga bisa memakan waktu beberapa saat.
            </x-slot>

            <div class="cek-baris">
                <x-filament::icon icon="heroicon-o-information-circle" class="cek-ikon-info" />
                <p class="cek-teks">Klik <strong>Jalankan Pemeriksaan</strong> di kanan atas untuk memulai.</p>
            </div>
        </x-filament::section>
    @elseif (count($issues) === 0)
        <x-filament::section>
            <div class="cek-baris">
                <x-filament::icon icon="heroicon-o-check-circle" class="cek-ikon cek-ikon-sukses" />

                <div>
                    <h3 class="cek-judul">Semua data valid</h3>
                    <p class="cek-teks">Tidak ditemukan masalah pada integritas stok maupun riwayat transaksi.</p>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="cek-baris">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="cek-ikon cek-ikon-peringatan" />

                <div>
                    <h3 class="cek-judul">Ditemukan {{ count($issues) }} masalah data</h3>
                    <p class="cek-teks">Perbaiki data berikut agar laporan stok dan nilai aset tetap akurat.</p>
                </div>
            </div>
        </x-filament::section>

        @foreach ($this->groupedIssues as $type => $typeIssues)
            <x-filament::section :heading="$type" collapsible>
                <x-slot name="headerEnd">
                    <x-filament::badge color="warning">{{ count($typeIssues) }} masalah</x-filament::badge>
                </x-slot>

                <div class="cek-gulir">
                    <table class="cek-tabel">
                        <thead>
                            <tr>
                                <th>Referensi</th>
                                <th>Masalah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($typeIssues as $issue)
                                <tr>
                                    <td class="cek-ref">{{ $issue['reference'] ?? '—' }}</td>
                                    <td>{{ $issue['issue'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
