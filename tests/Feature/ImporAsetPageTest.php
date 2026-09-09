<?php

namespace Tests\Feature;

use App\Filament\Pages\ImporAset;
use App\Models\Asset;
use App\Models\ImportBatch;
use App\Services\Import\AssetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Alur halaman impor: periksa, jalankan, batalkan.
 * Service-nya diuji terpisah; di sini yang dijaga adalah perekatnya.
 */
class ImporAsetPageTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanDataAcuan();
        Storage::fake(config('filesystems.default'));
    }

    /**
     * Menyimpan berkas ke disk seperti yang dilakukan FileUpload, lalu
     * mengembalikan bentuk state yang dipakai komponennya.
     *
     * @return array<string, string>
     */
    protected function berkasTersimpan(?string $isi = null): array
    {
        $kolom = array_keys(AssetImporter::KOLOM);

        $isi ??= implode(',', $kolom)."\n"
            .'Laptop,Lenovo,ThinkPad T14,PF-0001,,Baik,JKT,Sinar Terang,INV/1,2026-01-15,18500000,36,'."\n"
            .'Laptop,Lenovo,ThinkPad T14,PF-0002,,Baik,JKT,Sinar Terang,INV/1,2026-01-15,18500000,36,'."\n";

        $path = 'imports/aset-lama.csv';
        Storage::disk(config('filesystems.default'))->put($path, $isi);

        // FileUpload menyimpan state sebagai array berkunci acak.
        return ['abc123' => $path];
    }

    public function test_periksa_menampilkan_ringkasan_tanpa_menulis(): void
    {
        $pratinjau = Livewire::test(ImporAset::class)
            ->set('data.berkas', $this->berkasTersimpan())
            ->call('periksa')
            ->get('pratinjau');

        $this->assertSame(2, $pratinjau['ringkas']['total']);
        $this->assertSame(2, $pratinjau['ringkas']['sah']);
        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
    }

    public function test_jalankan_membuat_unit_dan_mencatat_batch(): void
    {
        Livewire::test(ImporAset::class)
            ->set('data.berkas', $this->berkasTersimpan())
            ->call('jalankan');

        $this->assertSame(2, Asset::withoutGlobalScopes()->count());

        $batch = ImportBatch::firstOrFail();
        $this->assertSame('done', $batch->status);
        $this->assertSame(2, $batch->created_count);
        $this->assertSame(0, $batch->failed_count);
    }

    public function test_baris_bermasalah_dilewati_dan_dilaporkan(): void
    {
        $kolom = implode(',', array_keys(AssetImporter::KOLOM));
        $isi = $kolom."\n"
            .'Laptop,Lenovo,ThinkPad T14,PF-0001,,Baik,JKT,,,,,,'."\n"
            .'Laptop,Dell,Latitude,,,Baik,JKT,,,,,,'."\n";  // serial kosong pada kategori wajib serial

        Livewire::test(ImporAset::class)
            ->set('data.berkas', $this->berkasTersimpan($isi))
            ->call('jalankan');

        $batch = ImportBatch::firstOrFail();

        $this->assertSame(1, $batch->created_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame(1, Asset::withoutGlobalScopes()->count());
    }

    public function test_batalkan_menghapus_unit_hasil_impor(): void
    {
        $komponen = Livewire::test(ImporAset::class)
            ->set('data.berkas', $this->berkasTersimpan())
            ->call('jalankan');

        $batch = ImportBatch::firstOrFail();

        $komponen->call('batalkan', $batch->id);

        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
        $this->assertSame('reverted', $batch->refresh()->status);
    }

    public function test_periksa_tanpa_berkas_tidak_meledak(): void
    {
        Livewire::test(ImporAset::class)
            ->call('periksa')
            ->assertOk();

        $this->assertNull(Livewire::test(ImporAset::class)->get('pratinjau'));
        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
    }

    public function test_hanya_admin_pusat_yang_boleh_mengakses(): void
    {
        $this->assertTrue(ImporAset::canAccess());

        // Impor membuat barang dan pembelian sekaligus, jadi admin cabang
        // tidak boleh menjangkaunya.
        auth()->user()->update(['role' => 'admin_cabang']);

        $this->assertFalse(ImporAset::canAccess());
    }
}
