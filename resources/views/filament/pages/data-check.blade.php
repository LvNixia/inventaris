<x-filament-panels::page>
    @if (! $hasRun)
        <x-filament::section>
            <x-slot name="heading">Belum ada pemeriksaan dijalankan</x-slot>
            <x-slot name="description">
                Pemeriksaan menelusuri seluruh data aset dan transaksi, sehingga bisa memakan waktu beberapa saat.
            </x-slot>

            <div class="flex items-center gap-x-3 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 shrink-0" />
                <span>Klik <strong>Jalankan Pemeriksaan</strong> di kanan atas untuk memulai.</span>
            </div>
        </x-filament::section>
    @elseif (count($issues) === 0)
        <x-filament::section>
            <div class="flex items-start gap-x-4">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="h-8 w-8 shrink-0 text-success-600 dark:text-success-400"
                />

                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Semua data valid
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Tidak ditemukan masalah pada integritas stok maupun riwayat transaksi.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex items-start gap-x-4">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="h-8 w-8 shrink-0 text-warning-600 dark:text-warning-400"
                />

                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        Ditemukan {{ count($issues) }} masalah data
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Perbaiki data berikut agar laporan stok dan nilai aset tetap akurat.
                    </p>
                </div>
            </div>
        </x-filament::section>

        @foreach ($this->groupedIssues as $type => $typeIssues)
            <x-filament::section :heading="$type" collapsible>
                <x-slot name="headerEnd">
                    <x-filament::badge color="warning">
                        {{ count($typeIssues) }} masalah
                    </x-filament::badge>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-start dark:border-white/10">
                                <th class="w-56 py-2 pe-4 text-start font-medium text-gray-500 dark:text-gray-400">
                                    Referensi
                                </th>
                                <th class="py-2 text-start font-medium text-gray-500 dark:text-gray-400">
                                    Masalah
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($typeIssues as $issue)
                                <tr>
                                    <td class="py-2 pe-4 align-top font-medium text-gray-950 dark:text-white">
                                        {{ $issue['reference'] ?? '—' }}
                                    </td>
                                    <td class="py-2 align-top text-gray-600 dark:text-gray-300">
                                        {{ $issue['issue'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
