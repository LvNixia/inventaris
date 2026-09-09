<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\DisposalReason;
use App\Models\Product;
use App\Models\PurchaseBatch;
use App\Services\Import\AssetImporter;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

class AssetImporterTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanDataAcuan();
    }

    /**
     * Menulis berkas CSV sementara berisi judul kolom lengkap.
     *
     * @param  array<int, array<string, string>>  $baris
     */
    protected function berkas(array $baris): string
    {
        $kolom = array_keys(AssetImporter::KOLOM);
        $isi = implode(',', $kolom)."\n";

        foreach ($baris as $data) {
            $isi .= implode(',', array_map(fn (string $k): string => $data[$k] ?? '', $kolom))."\n";
        }

        $path = tempnam(sys_get_temp_dir(), 'impor').'.csv';
        file_put_contents($path, $isi);

        return $path;
    }

    protected function barisLaptop(string $serial = 'PF-0001'): array
    {
        return [
            'kategori' => 'Laptop',
            'merek' => 'Lenovo',
            'model' => 'ThinkPad T14 Gen 3',
            'serial_number' => $serial,
            'kondisi' => 'Baik',
            'cabang' => 'JKT',
            'vendor' => 'Sinar Terang Komputer',
            'no_faktur' => 'INV/2026/01/007',
            'tanggal_beli' => '2026-01-15',
            'harga_satuan' => '18500000',
            'garansi_bulan' => '36',
        ];
    }

    public function test_pratinjau_tidak_menulis_apa_pun(): void
    {
        $path = $this->berkas([$this->barisLaptop()]);

        $hasil = app(AssetImporter::class)->pratinjau($path);

        $this->assertSame(1, $hasil['ringkas']['total']);
        $this->assertSame(1, $hasil['ringkas']['sah']);
        $this->assertSame(1, $hasil['ringkas']['barang_baru']);
        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
    }

    public function test_impor_membuat_barang_pembelian_dan_unit(): void
    {
        $path = $this->berkas([$this->barisLaptop('PF-0001'), $this->barisLaptop('PF-0002')]);

        $batch = app(AssetImporter::class)->jalankan($path, 'aset-lama.csv');

        $this->assertSame('done', $batch->status);
        $this->assertSame(2, $batch->created_count);

        // Dua unit dari satu faktur yang sama: satu barang, satu batch pembelian.
        $this->assertSame(1, Product::count());
        $this->assertSame(1, PurchaseBatch::count());
        $this->assertSame(2, Asset::withoutGlobalScopes()->count());

        $unit = Asset::withoutGlobalScopes()->first();
        $this->assertSame('registered', $unit->currentStatus->code);
        $this->assertSame(18500000.0, (float) $unit->unit_price);
        $this->assertSame('2029-01-15', $unit->purchaseBatch->warranty_until->toDateString());
    }

    public function test_baris_bermasalah_ditolak_tanpa_menggagalkan_sisanya(): void
    {
        $path = $this->berkas([
            $this->barisLaptop('PF-0001'),
            ['kategori' => 'Laptop', 'merek' => 'Dell', 'cabang' => 'JKT'], // serial kosong
            array_merge($this->barisLaptop('PF-0003'), ['cabang' => 'Surabaya']), // cabang tak dikenal
        ]);

        $hasil = app(AssetImporter::class)->pratinjau($path);

        $this->assertSame(3, $hasil['ringkas']['total']);
        $this->assertSame(1, $hasil['ringkas']['sah']);
        $this->assertStringContainsString('mewajibkan nomor seri', implode(' ', $hasil['galat']));
        $this->assertStringContainsString('tidak dikenal', implode(' ', $hasil['galat']));

        $batch = app(AssetImporter::class)->jalankan($path, 'campuran.csv');

        $this->assertSame(1, $batch->created_count);
        $this->assertSame(2, $batch->failed_count);
        $this->assertSame(1, Asset::withoutGlobalScopes()->count());
    }

    public function test_serial_ganda_di_dalam_berkas_ditolak(): void
    {
        $path = $this->berkas([$this->barisLaptop('PF-SAMA'), $this->barisLaptop('PF-SAMA')]);

        $hasil = app(AssetImporter::class)->pratinjau($path);

        $this->assertSame(1, $hasil['ringkas']['sah']);
        $this->assertStringContainsString('muncul dua kali', implode(' ', $hasil['galat']));
    }

    public function test_serial_yang_sudah_terdaftar_ditolak(): void
    {
        app(AssetImporter::class)->jalankan($this->berkas([$this->barisLaptop('PF-0001')]), 'satu.csv');

        $hasil = app(AssetImporter::class)->pratinjau($this->berkas([$this->barisLaptop('PF-0001')]));

        $this->assertSame(0, $hasil['ringkas']['sah']);
        $this->assertStringContainsString('sudah terdaftar', implode(' ', $hasil['galat']));
    }

    public function test_impor_kedua_memakai_ulang_barang_yang_sama(): void
    {
        $importer = app(AssetImporter::class);

        $importer->jalankan($this->berkas([$this->barisLaptop('PF-0001')]), 'satu.csv');
        $importer->jalankan($this->berkas([$this->barisLaptop('PF-0002')]), 'dua.csv');

        $this->assertSame(1, Product::count());
        $this->assertSame(1, PurchaseBatch::count(), 'Faktur yang sama tidak boleh melahirkan batch kedua.');
        $this->assertSame(2, Asset::withoutGlobalScopes()->count());
    }

    public function test_pembatalan_menghapus_unit_dan_batch_pembeliannya(): void
    {
        $importer = app(AssetImporter::class);
        $batch = $importer->jalankan($this->berkas([$this->barisLaptop('PF-0001')]), 'satu.csv');

        $jumlah = $importer->batalkan($batch);

        $this->assertSame(1, $jumlah);
        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
        $this->assertSame(0, PurchaseBatch::count());
        $this->assertSame('reverted', $batch->refresh()->status);
    }

    public function test_pembatalan_ditolak_bila_unitnya_sudah_dipakai(): void
    {
        $importer = app(AssetImporter::class);
        $batch = $importer->jalankan($this->berkas([$this->barisLaptop('PF-0001')]), 'satu.csv');

        app(TransactionService::class)->dispose(Asset::withoutGlobalScopes()->first(), [
            'disposal_reason_id' => DisposalReason::where('code', 'dibuang')->value('id'),
        ]);

        $this->expectExceptionMessage('sudah dipakai');

        $importer->batalkan($batch->refresh());
    }

    public function test_berkas_contoh_memuat_seluruh_kolom(): void
    {
        $contoh = app(AssetImporter::class)->berkasContoh();
        [$judul, $baris] = explode("\n", trim($contoh));

        $this->assertSame(array_keys(AssetImporter::KOLOM), explode(',', $judul));
        $this->assertCount(count(AssetImporter::KOLOM), explode(',', $baris));
    }
}
