<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ServiceKind;
use App\Services\BranchTransferService;
use App\Services\HandoverService;
use App\Services\ServiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Menjaga janji utama model per unit: satu baris aset berarti satu unit fisik,
 * sehingga keadaan satu unit tidak pernah menular ke unit lain yang sejenis.
 */
class PerUnitAssetTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanDataAcuan();
    }

    public function test_pembelian_membuat_satu_baris_aset_per_unit(): void
    {
        $units = $this->beli($this->barang(), 8);

        $this->assertCount(8, $units);
        $this->assertSame(8, Asset::withoutGlobalScopes()->count());
        // Semua unit menunjuk ke satu batch dan satu barang yang sama.
        $this->assertSame(1, $units->pluck('purchase_batch_id')->unique()->count());
        $this->assertSame(1, $units->pluck('product_id')->unique()->count());
        // Kode unit tidak boleh bertabrakan.
        $this->assertSame(8, $units->pluck('asset_code')->unique()->count());
    }

    public function test_akhir_garansi_dihitung_dari_tanggal_beli(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();

        $this->assertEquals(
            now()->subMonth()->addMonths(12)->toDateString(),
            $unit->purchaseBatch->warranty_until->toDateString(),
        );
    }

    public function test_pembelian_ulang_menambah_batch_bukan_barang_baru(): void
    {
        $product = $this->barang();

        $this->beli($product, 8);
        $this->beli($product, 4);

        $this->assertSame(1, Product::count());
        $this->assertSame(2, $product->purchaseBatches()->count());
        $this->assertSame(12, $product->assets()->count());
    }

    /**
     * Inti dari perombakan ini: dulu satu unit masuk servis membuat seluruh lot
     * berstatus Servis dan tidak bisa diserahkan.
     */
    public function test_servis_satu_unit_tidak_mengunci_unit_sejenis(): void
    {
        $units = $this->beli($this->barang(), 8);
        $rusak = $units->first();

        app(ServiceService::class)->open($rusak, [
            'service_kind_id' => ServiceKind::where('code', 'perbaikan')->value('id'),
            'performed_by' => 'internal',
            'item_left' => true,
            'started_at' => now(),
            'complaint' => 'Satu unit tidak responsif.',
        ]);

        $this->assertFalse($rusak->refresh()->isAvailable());
        $this->assertSame(7, Asset::withoutGlobalScopes()->available()->count());
    }

    public function test_serial_wajib_menahan_serah_terima_sampai_diisi(): void
    {
        // Laptop wajib bernomor seri; unit ini sengaja diterima tanpa serial.
        $unit = $this->beli($this->barang('LAP', 'ThinkPad T14'), 1)->first();

        $penerima = $this->karyawan();
        $doc = $this->draf($unit, $penerima);

        $this->assertTrue($unit->isMissingRequiredSerial());

        try {
            app(HandoverService::class)->issue($doc);
            $this->fail('Serah terima seharusnya ditolak karena nomor seri kosong.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Nomor seri', $e->getMessage());
        }

        // Setelah serial dilengkapi, surat yang sama bisa diterbitkan.
        $unit->update(['serial_number' => 'PF-UJI-0001']);

        app(HandoverService::class)->issue($doc->refresh());

        $this->assertSame('issued', $doc->refresh()->status);
        $this->assertSame($penerima->id, $unit->refresh()->current_holder_id);
    }

    public function test_serial_wajib_menahan_kirim_antar_cabang(): void
    {
        $unit = $this->beli($this->barang('LAP', 'Latitude 5430'), 1)->first();

        $this->expectExceptionMessage('Nomor seri');

        app(BranchTransferService::class)->send($unit, [
            'to_branch_id' => Branch::where('code', 'BTM')->value('id'),
        ]);
    }

    public function test_serial_wajib_menahan_barang_ditinggal_di_servis(): void
    {
        $unit = $this->beli($this->barang('LAP', 'ProBook 440'), 1)->first();

        $this->expectExceptionMessage('Nomor seri');

        app(ServiceService::class)->open($unit, [
            'service_kind_id' => ServiceKind::where('code', 'perbaikan')->value('id'),
            'performed_by' => 'vendor',
            'item_left' => true,
            'started_at' => now(),
            'complaint' => 'Layar berkedip.',
        ]);
    }

    public function test_barang_tanpa_serial_tidak_ikut_dipagari(): void
    {
        // Aksesoris tidak wajib bernomor seri, jadi boleh diserahkan apa adanya.
        $unit = $this->beli($this->barang(), 1)->first();

        $this->assertFalse($unit->isMissingRequiredSerial());

        app(HandoverService::class)->issue($this->draf($unit, $this->karyawan()));

        $this->assertNotNull($unit->refresh()->current_holder_id);
    }

    public function test_kirim_antar_cabang_memindahkan_unit_yang_sama(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $kodeAwal = $unit->asset_code;

        app(BranchTransferService::class)->send($unit, [
            'to_branch_id' => $this->cabang('BTM')->id,
            'notes' => 'Resi uji.',
        ]);

        $unit->refresh();

        // Tidak ada lagi pemecahan baris: kode unitnya tetap, sehingga riwayat
        // servis dan lampirannya ikut terbawa ke cabang tujuan.
        $this->assertSame($kodeAwal, $unit->asset_code);
        $this->assertSame($this->cabang('BTM')->id, $unit->branch_id);
        $this->assertSame('in_transit', $unit->currentStatus->code);
        $this->assertSame(1, Asset::withoutGlobalScopes()->count());
    }
}
