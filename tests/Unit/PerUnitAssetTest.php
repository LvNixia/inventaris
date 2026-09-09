<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Employee;
use App\Models\HandoverDocument;
use App\Models\Product;
use App\Models\ServiceKind;
use App\Models\User;
use App\Services\AssetService;
use App\Services\BranchTransferService;
use App\Services\HandoverService;
use App\Services\ServiceService;
use Database\Seeders\MasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Menjaga janji utama model per unit: satu baris aset berarti satu unit fisik,
 * sehingga keadaan satu unit tidak pernah menular ke unit lain yang sejenis.
 */
class PerUnitAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MasterSeeder::class);

        $admin = new User;
        $admin->name = 'Admin Uji';
        $admin->email = 'uji@contoh.test';
        $admin->password = 'rahasia-uji';
        $admin->role = 'admin_pusat';
        $admin->is_active = true;
        $admin->save();

        Auth::login($admin);
    }

    protected function barang(string $kategoriPrefix = 'ACC', string $model = 'MK270'): Product
    {
        return Product::create([
            'category_id' => Category::where('code_prefix', $kategoriPrefix)->value('id'),
            'brand_id' => Brand::firstOrCreate(['name' => 'Logitech'], ['is_active' => true])->id,
            'model' => $model,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $units
     */
    protected function beli(Product $product, int $jumlah = 1, ?array $units = null)
    {
        return app(AssetService::class)->receivePurchase([
            'product_id' => $product->id,
            'branch_id' => Branch::where('code', 'JKT')->value('id'),
            'invoice_number' => 'INV/UJI/'.uniqid(),
            'purchase_date' => now()->subMonth(),
            'unit_price' => 350000,
            'warranty_months' => 12,
        ], $units ?? array_fill(0, $jumlah, ['serial_number' => null]));
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

    public function test_barang_tanpa_serial_tidak_ikut_dipagari(): void
    {
        // Aksesoris tidak wajib bernomor seri, jadi boleh diserahkan apa adanya.
        $unit = $this->beli($this->barang(), 1)->first();

        $this->assertFalse($unit->isMissingRequiredSerial());

        app(HandoverService::class)->issue($this->draf($unit, $this->karyawan()));

        $this->assertNotNull($unit->refresh()->current_holder_id);
    }

    protected function karyawan(): Employee
    {
        return Employee::create([
            'name' => 'Penerima Uji',
            'nik' => 'UJI-'.uniqid(),
            'branch_id' => Branch::where('code', 'JKT')->value('id'),
            'is_active' => true,
        ]);
    }

    protected function draf(Asset $unit, Employee $penerima): HandoverDocument
    {
        $penyerah = Employee::create([
            'name' => 'Penyerah Uji',
            'nik' => 'UJI-'.uniqid(),
            'branch_id' => Branch::where('code', 'JKT')->value('id'),
            'is_active' => true,
        ]);

        $doc = HandoverDocument::create([
            'document_date' => now(),
            'branch_id' => Branch::where('code', 'JKT')->value('id'),
            'first_party_id' => $penyerah->id,
            'second_party_id' => $penerima->id,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $doc->items()->create([
            'asset_id' => $unit->id,
            'user_employee_id' => $penerima->id,
        ]);

        return $doc;
    }
}
