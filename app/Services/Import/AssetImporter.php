<?php

namespace App\Services\Import;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\PurchaseBatch;
use App\Models\Vendor;
use App\Services\AssetCodeGenerator;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Memasukkan data aset lama dari berkas XLSX atau CSV.
 *
 * Impor dibagi dua tahap. Tahap pertama membaca dan memvalidasi seluruh baris
 * tanpa menyentuh basis data, sehingga pengguna bisa melihat lebih dulu apa
 * yang akan terjadi. Tahap kedua baru menulis, seluruhnya dalam satu transaksi.
 *
 * Barang, batch pembelian, dan vendor dibuat otomatis bila belum ada, agar
 * satu berkas cukup untuk memindahkan inventaris yang sudah berjalan.
 */
class AssetImporter
{
    /**
     * Kolom berkas beserta keterangannya. Dipakai untuk memvalidasi judul
     * kolom sekaligus membuat berkas contoh.
     *
     * @var array<string, string>
     */
    public const KOLOM = [
        'kategori' => 'Wajib. Nama atau kode kategori, misalnya Laptop atau LAP.',
        'merek' => 'Wajib. Dibuat otomatis bila belum terdaftar.',
        'model' => 'Tipe barang, misalnya ThinkPad T14 Gen 3.',
        'serial_number' => 'Wajib untuk kategori bernomor seri. Untuk lisensi, isi kunci produknya. Harus unik.',
        'imei_1' => 'Khusus ponsel.',
        'kondisi' => 'Baik, Rusak Ringan, Rusak Berat, atau Hilang. Kosong berarti Baik.',
        'cabang' => 'Wajib. Nama atau kode cabang, misalnya Batam atau BTM.',
        'vendor' => 'Nama toko atau vendor. Dibuat otomatis bila belum terdaftar.',
        'no_faktur' => 'Nomor faktur pembelian.',
        'tanggal_beli' => 'Format YYYY-MM-DD.',
        'harga_satuan' => 'Angka tanpa titik atau koma.',
        'garansi_bulan' => 'Lama garansi dalam bulan.',
        'catatan' => 'Keterangan bebas.',
    ];

    protected const KOLOM_WAJIB = ['kategori', 'merek', 'cabang'];

    public function __construct(protected AssetCodeGenerator $codeGenerator) {}

    /**
     * Baca dan periksa berkas tanpa menulis apa pun.
     *
     * @return array{baris: Collection<int, array<string, mixed>>, galat: array<int, string>, ringkas: array<string, int>}
     */
    public function pratinjau(string $path): array
    {
        $baris = $this->baca($path);
        $galat = [];
        $serialTerpakai = [];
        $sah = 0;

        foreach ($baris as $i => $data) {
            $nomorBaris = $i + 2; // baris 1 adalah judul kolom
            $pesan = $this->periksaBaris($data, $serialTerpakai);

            if ($pesan === []) {
                $sah++;

                if (filled($data['serial_number'])) {
                    $serialTerpakai[] = mb_strtolower($data['serial_number']);
                }
            }

            $galat = array_merge($galat, array_map(
                fn (string $p): string => "Baris {$nomorBaris}: {$p}",
                $pesan,
            ));

            $baris[$i]['_galat'] = $pesan;
        }

        return [
            'baris' => collect($baris),
            'galat' => $galat,
            'ringkas' => [
                'total' => count($baris),
                'sah' => $sah,
                'gagal' => count($baris) - $sah,
                'barang_baru' => $this->hitungBarangBaru($baris),
            ],
        ];
    }

    /**
     * Tulis seluruh baris yang lolos pemeriksaan.
     *
     * @throws Exception
     */
    public function jalankan(string $path, string $namaBerkas): ImportBatch
    {
        $hasil = $this->pratinjau($path);

        if ($hasil['ringkas']['sah'] === 0) {
            throw new Exception('Tidak ada baris yang bisa diimpor. Perbaiki berkasnya lebih dulu.');
        }

        return DB::transaction(function () use ($hasil, $namaBerkas): ImportBatch {
            $batch = ImportBatch::create([
                'type' => 'asset',
                'file_name' => $namaBerkas,
                'user_id' => auth()->id(),
                'branch_id' => auth()->user()?->branch_id,
                'total' => $hasil['ringkas']['total'],
                'failed_count' => $hasil['ringkas']['gagal'],
                'status' => 'processing',
            ]);

            $registered = AssetStatus::where('code', 'registered')->value('id');
            $baik = Condition::where('code', 'baik')->value('id');
            $dibuat = 0;

            foreach ($hasil['baris'] as $data) {
                if ($data['_galat'] !== []) {
                    continue;
                }

                $product = $this->cariAtauBuatBarang($data);
                $batchBeli = $this->cariAtauBuatPembelian($product, $data, $batch);

                Asset::create([
                    'asset_code' => $this->codeGenerator->generate($product->category),
                    'product_id' => $product->id,
                    'purchase_batch_id' => $batchBeli?->id,
                    'branch_id' => $this->cabang($data['cabang'])->id,
                    'serial_number' => blank($data['serial_number']) ? null : $data['serial_number'],
                    'imei_1' => blank($data['imei_1']) ? null : $data['imei_1'],
                    'condition_id' => $this->kondisi($data['kondisi'])?->id ?? $baik,
                    'current_status_id' => $registered,
                    'notes' => blank($data['catatan']) ? null : $data['catatan'],
                    'import_batch_id' => $batch->id,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                $dibuat++;
            }

            $batch->update([
                'created_count' => $dibuat,
                'status' => 'done',
            ]);

            return $batch->refresh();
        });
    }

    /**
     * Batalkan satu impor. Hanya unit yang belum bergerak yang boleh dihapus,
     * supaya riwayat transaksi tidak pernah menggantung tanpa asetnya.
     *
     * @throws Exception
     */
    public function batalkan(ImportBatch $batch): int
    {
        if ($batch->status !== 'done') {
            throw new Exception('Hanya impor yang berhasil yang bisa dibatalkan.');
        }

        return DB::transaction(function () use ($batch): int {
            $units = Asset::withoutGlobalScopes()
                ->where('import_batch_id', $batch->id)
                ->get();

            $terpakai = $units->filter(fn (Asset $unit): bool => $unit->assetTransactions()->exists()
                || $unit->handoverItems()->exists()
                || $unit->assetServices()->exists());

            if ($terpakai->isNotEmpty()) {
                throw new Exception(
                    'Impor tidak bisa dibatalkan karena '.$terpakai->count().' unit sudah dipakai: '
                    .$terpakai->take(5)->pluck('asset_code')->join(', ')
                    .($terpakai->count() > 5 ? ', dan lainnya' : '').'.'
                );
            }

            $jumlah = $units->count();

            Asset::withoutGlobalScopes()->where('import_batch_id', $batch->id)->delete();

            // Batch pembelian yang lahir dari impor ini ikut dibersihkan bila
            // sudah tidak menaungi unit mana pun.
            PurchaseBatch::where('import_batch_id', $batch->id)
                ->whereDoesntHave('assets')
                ->delete();

            $batch->update(['status' => 'reverted']);

            return $jumlah;
        });
    }

    /**
     * Berkas contoh berisi judul kolom dan satu baris petunjuk.
     */
    public function berkasContoh(): string
    {
        $judul = implode(',', array_keys(self::KOLOM));
        $contoh = implode(',', [
            'Laptop', 'Lenovo', 'ThinkPad T14 Gen 3', 'PF3ABC12', '',
            'Baik', 'JKT', 'Sinar Terang Komputer', 'INV/2026/01/007',
            '2026-01-15', '18500000', '36', 'Warisan data lama',
        ]);

        return $judul."\n".$contoh."\n";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function baca(string $path): array
    {
        $reader = str_ends_with(mb_strtolower($path), '.csv') ? new CsvReader : new XlsxReader;
        $reader->open($path);

        $judul = null;
        $baris = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $nilai = array_map(
                    fn ($cell) => is_string($cell->getValue()) ? trim($cell->getValue()) : $cell->getValue(),
                    $row->getCells(),
                );

                if ($judul === null) {
                    $judul = array_map(
                        fn ($v): string => str_replace(' ', '_', mb_strtolower((string) $v)),
                        $nilai,
                    );

                    continue;
                }

                if (collect($nilai)->filter(fn ($v) => filled($v))->isEmpty()) {
                    continue; // baris kosong
                }

                $data = [];

                foreach (array_keys(self::KOLOM) as $kolom) {
                    $posisi = array_search($kolom, $judul, true);
                    $data[$kolom] = $posisi === false ? null : ($nilai[$posisi] ?? null);
                }

                $baris[] = $data;
            }

            break; // hanya lembar pertama
        }

        $reader->close();

        return $baris;
    }

    /**
     * @param  array<int, string>  $serialTerpakai
     * @return array<int, string>
     */
    protected function periksaBaris(array $data, array $serialTerpakai): array
    {
        $pesan = [];

        foreach (self::KOLOM_WAJIB as $kolom) {
            if (blank($data[$kolom])) {
                $pesan[] = "kolom {$kolom} wajib diisi";
            }
        }

        $kategori = filled($data['kategori']) ? $this->kategori($data['kategori']) : null;

        if (filled($data['kategori']) && ! $kategori) {
            $pesan[] = "kategori \"{$data['kategori']}\" tidak dikenal";
        }

        if (filled($data['cabang']) && ! $this->cabang($data['cabang'])) {
            $pesan[] = "cabang \"{$data['cabang']}\" tidak dikenal";
        }

        if (filled($data['kondisi']) && ! $this->kondisi($data['kondisi'])) {
            $pesan[] = "kondisi \"{$data['kondisi']}\" tidak dikenal";
        }

        if ($kategori?->requires_serial && blank($data['serial_number'])) {
            $pesan[] = "kategori {$kategori->name} mewajibkan ".($kategori->code_prefix === 'LSS' ? 'kunci lisensi' : 'nomor seri');
        }

        if (filled($data['serial_number'])) {
            $serial = mb_strtolower($data['serial_number']);

            if (in_array($serial, $serialTerpakai, true)) {
                $pesan[] = "nomor seri \"{$data['serial_number']}\" muncul dua kali di berkas ini";
            } elseif (Asset::withoutGlobalScopes()->where('serial_number', $data['serial_number'])->exists()) {
                $pesan[] = "nomor seri \"{$data['serial_number']}\" sudah terdaftar";
            }
        }

        if (filled($data['tanggal_beli']) && ! $this->tanggal($data['tanggal_beli'])) {
            $pesan[] = "tanggal_beli \"{$data['tanggal_beli']}\" tidak terbaca; pakai format YYYY-MM-DD";
        }

        if (filled($data['harga_satuan']) && ! is_numeric(str_replace(['.', ',', ' '], '', (string) $data['harga_satuan']))) {
            $pesan[] = 'harga_satuan harus berupa angka';
        }

        return $pesan;
    }

    protected function hitungBarangBaru(array $baris): int
    {
        return collect($baris)
            ->filter(fn (array $d): bool => filled($d['kategori']) && filled($d['merek']))
            ->map(fn (array $d): string => mb_strtolower($d['kategori'].'|'.$d['merek'].'|'.($d['model'] ?? '')))
            ->unique()
            ->reject(function (string $kunci): bool {
                [$kategori, $merek, $model] = explode('|', $kunci);

                return Product::whereHas('category', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$kategori])->orWhereRaw('LOWER(code_prefix) = ?', [$kategori]))
                    ->whereHas('brand', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$merek]))
                    ->where(fn ($q) => $q->whereRaw('LOWER(COALESCE(model, "")) = ?', [$model]))
                    ->exists();
            })
            ->count();
    }

    protected function cariAtauBuatBarang(array $data): Product
    {
        $kategori = $this->kategori($data['kategori']);
        $merek = Brand::firstOrCreate(
            ['name' => $data['merek']],
            ['is_active' => true],
        );

        return Product::firstOrCreate(
            [
                'category_id' => $kategori->id,
                'brand_id' => $merek->id,
                'model' => blank($data['model']) ? null : $data['model'],
            ],
            ['is_active' => true],
        );
    }

    /**
     * Baris dengan faktur, tanggal, dan harga yang sama dianggap satu pembelian,
     * sehingga satu faktur berisi sepuluh unit tidak melahirkan sepuluh batch.
     */
    protected function cariAtauBuatPembelian(Product $product, array $data, ImportBatch $batch): ?PurchaseBatch
    {
        $tanggal = $this->tanggal($data['tanggal_beli']);
        $harga = filled($data['harga_satuan'])
            ? (float) str_replace(['.', ',', ' '], '', (string) $data['harga_satuan'])
            : null;

        if (blank($data['no_faktur']) && ! $tanggal && $harga === null) {
            return null; // tidak ada data pembelian sama sekali
        }

        $garansi = filled($data['garansi_bulan']) ? (int) $data['garansi_bulan'] : null;
        $faktur = blank($data['no_faktur']) ? null : $data['no_faktur'];

        // Dicari manual, bukan lewat firstOrCreate, karena kolom tanggal
        // disimpan beserta jamnya sehingga pembandingan nilai mentah tidak
        // pernah cocok, dan nilai null butuh whereNull.
        $cocok = PurchaseBatch::where('product_id', $product->id)
            ->when($faktur, fn ($q) => $q->where('invoice_number', $faktur), fn ($q) => $q->whereNull('invoice_number'))
            ->when($tanggal, fn ($q) => $q->whereDate('purchase_date', $tanggal->toDateString()), fn ($q) => $q->whereNull('purchase_date'))
            ->when($harga !== null, fn ($q) => $q->where('unit_price', $harga), fn ($q) => $q->whereNull('unit_price'))
            ->first();

        if ($cocok) {
            return $cocok;
        }

        return PurchaseBatch::create([
            'product_id' => $product->id,
            'invoice_number' => $faktur,
            'purchase_date' => $tanggal?->toDateString(),
            'unit_price' => $harga,
            'branch_id' => $this->cabang($data['cabang'])->id,
            'vendor_id' => filled($data['vendor'])
                ? Vendor::firstOrCreate(['name' => $data['vendor']], ['type' => 'toko', 'is_active' => true])->id
                : null,
            'warranty_months' => $garansi,
            'warranty_until' => $tanggal && $garansi ? $tanggal->copy()->addMonths($garansi)->toDateString() : null,
            'import_batch_id' => $batch->id,
            'created_by' => auth()->id(),
        ]);
    }

    protected function kategori(?string $nilai): ?Category
    {
        if (blank($nilai)) {
            return null;
        }

        return Category::whereRaw('LOWER(name) = ?', [mb_strtolower($nilai)])
            ->orWhereRaw('LOWER(code_prefix) = ?', [mb_strtolower($nilai)])
            ->first();
    }

    protected function cabang(?string $nilai): ?Branch
    {
        if (blank($nilai)) {
            return null;
        }

        return Branch::whereRaw('LOWER(name) = ?', [mb_strtolower($nilai)])
            ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($nilai)])
            ->first();
    }

    protected function kondisi(?string $nilai): ?Condition
    {
        if (blank($nilai)) {
            return null;
        }

        return Condition::whereRaw('LOWER(name) = ?', [mb_strtolower($nilai)])
            ->orWhereRaw('LOWER(code) = ?', [mb_strtolower($nilai)])
            ->first();
    }

    protected function tanggal(mixed $nilai): ?Carbon
    {
        if (blank($nilai)) {
            return null;
        }

        if ($nilai instanceof \DateTimeInterface) {
            return Carbon::instance($nilai);
        }

        try {
            return Carbon::parse((string) $nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Simpan berkas unggahan ke lokasi sementara yang bisa dibaca ulang.
     */
    public function simpanSementara(string $path): string
    {
        return Storage::disk(config('filesystems.default'))->path($path);
    }
}
