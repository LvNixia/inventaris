<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\PurchaseBatch;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GoodsReceiptService;
use App\Services\HandoverService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Penerimaan barang: pemeriksaan sebelum disetujui, pembuatan unit aset, dan
 * sambungannya ke modul lama.
 */
class GoodsReceiptTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->siapkanDataAcuan();
    }

    protected function vendor(): Vendor
    {
        return Vendor::firstOrCreate(['name' => 'Sinar Terang Komputer'], [
            'type' => 'toko',
            'payment_term_id' => PaymentTerm::where('code', 'net30')->value('id'),
            'is_active' => true,
        ]);
    }

    /**
     * PO yang sudah disetujui, satu baris berisi $qty unit.
     */
    protected function poDisetujui(Product $produk, int $qty = 2, float $harga = 18_500_000, int $garansi = 36): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'po_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $po->items()->create([
            'product_id' => $produk->id,
            'quantity' => $qty,
            'unit_price' => $harga,
            'warranty_months' => $garansi,
        ]);

        $svc = app(PurchaseOrderService::class);
        $po = $svc->submit($po->refresh());

        // Pengaju tidak boleh menyetujui pengajuannya sendiri.
        Auth::login($this->adminKedua());
        $po = $svc->approve($po);
        Auth::login($this->admin);

        return $po;
    }

    protected function adminKedua(): User
    {
        return User::firstOrCreate(['email' => 'kedua@contoh.test'], [
            'name' => 'Admin Kedua',
            'password' => 'rahasia-uji',
            'role' => 'admin_pusat',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, ?string>  $serials
     */
    protected function gr(PurchaseOrder $po, array $serials): GoodsReceipt
    {
        $poItem = $po->items->first();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($serials as $serial) {
            $gr->items()->create([
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'serial_number' => $serial,
                'unit_price' => $poItem->unit_price,
                'warranty_months' => $poItem->warranty_months,
            ]);
        }

        return $gr->refresh();
    }

    public function test_penerimaan_melahirkan_unit_lengkap_dengan_harga_dan_garansi(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'ThinkPad T14'), 2);
        $gr = app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-0001', 'PF-0002']));

        $this->assertSame('received', $gr->status);
        $this->assertStringStartsWith('GR/JKT/', $gr->gr_number);
        $this->assertSame(2, Asset::withoutGlobalScopes()->count());

        $unit = Asset::withoutGlobalScopes()->where('serial_number', 'PF-0001')->first();

        $this->assertNotNull($unit, 'Unit dengan serial dari penerimaan harus ada.');
        $this->assertSame('registered', $unit->currentStatus->code);
        $this->assertSame('baik', $unit->condition->code);
        $this->assertSame(18_500_000.0, (float) $unit->unit_price);
        $this->assertSame(now()->toDateString(), $unit->purchase_date->toDateString());
        $this->assertSame(now()->addMonths(36)->toDateString(), $unit->warranty_until->toDateString());
    }

    /**
     * Sepuluh unit dari satu baris pesanan adalah satu pembelian, bukan sepuluh.
     */
    public function test_unit_seharga_sama_berbagi_satu_batch_pembelian(): void
    {
        $po = $this->poDisetujui($this->barang('ACC', 'MK270'), 5, 350_000);
        app(GoodsReceiptService::class)->receive($this->gr($po, [null, null, null, null, null]));

        $this->assertSame(5, Asset::withoutGlobalScopes()->count());
        $this->assertSame(1, PurchaseBatch::count());
        $this->assertSame(5, PurchaseBatch::first()->assets()->count());
    }

    public function test_unit_hasil_penerimaan_langsung_bisa_diserahkan(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'Latitude 5430'), 1);
        app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-9001']));

        $unit = Asset::withoutGlobalScopes()->first();
        $penerima = $this->karyawan();

        app(HandoverService::class)->issue($this->draf($unit, $penerima));

        $this->assertSame($penerima->id, $unit->refresh()->current_holder_id);
    }

    public function test_serial_kosong_pada_kategori_wajib_ditolak(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'ProBook 440'), 2);

        try {
            app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-1001', null]));
            $this->fail('Penerimaan seharusnya ditolak karena ada serial kosong.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Nomor Seri belum diisi', $e->getMessage());
        }

        // Ditolak seluruhnya: tidak boleh ada unit yang terlanjur lahir.
        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
        $this->assertSame(0, PurchaseBatch::count());
    }

    public function test_lisensi_memakai_sebutan_kunci_lisensi(): void
    {
        $po = $this->poDisetujui($this->barang('LSS', 'Microsoft 365'), 1, 2_100_000);

        $this->expectExceptionMessage('Kunci Lisensi belum diisi');

        app(GoodsReceiptService::class)->receive($this->gr($po, [null]));
    }

    public function test_serial_kembar_di_dalam_satu_penerimaan_ditolak(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'Vostro 3520'), 2);

        $this->expectExceptionMessage('muncul dua kali');

        app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-SAMA', 'PF-SAMA']));
    }

    public function test_serial_yang_sudah_terdaftar_ditolak(): void
    {
        $produk = $this->barang('LAP', 'ThinkPad E14');
        app(GoodsReceiptService::class)->receive($this->gr($this->poDisetujui($produk, 1), ['PF-2001']));

        $this->expectExceptionMessage('sudah terdaftar pada aset lain');

        app(GoodsReceiptService::class)->receive($this->gr($this->poDisetujui($produk, 1), ['PF-2001']));
    }

    public function test_seluruh_kesalahan_dilaporkan_sekaligus(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'ExpertBook'), 3);

        try {
            app(GoodsReceiptService::class)->receive($this->gr($po, [null, null, null]));
            $this->fail('Seharusnya ditolak.');
        } catch (\Exception $e) {
            // Tiga baris pesan, bukan satu: pengguna melihat semua kekurangan
            // dalam sekali coba.
            $this->assertCount(3, explode("\n", $e->getMessage()));
        }
    }

    public function test_terima_sebagian_menaikkan_pesanan_ke_partial_receipt(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'ProBook 450'), 3);

        app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-3001']));

        $this->assertSame('partial_receipt', $po->refresh()->status);
        $this->assertSame(1, $po->items->first()->received_quantity);
    }

    public function test_terima_penuh_menutup_pesanan(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'ProBook 460'), 2);

        app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-4001', 'PF-4002']));

        $this->assertSame('completed', $po->refresh()->status);
    }

    public function test_terima_melebihi_sisa_pesanan_ditolak(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'ProBook 470'), 2);

        app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-5001']));

        $this->expectExceptionMessage('sisa pesanan hanya 1');

        app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-5002', 'PF-5003']));
    }

    /**
     * Pembelian mendadak, hibah, dan retur tidak punya pesanan.
     */
    public function test_penerimaan_tanpa_pesanan_tetap_bisa_disetujui(): void
    {
        $gr = GoodsReceipt::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $gr->items()->create([
            'product_id' => $this->barang('ACC', 'Kabel HDMI')->id,
            'unit_price' => 75_000,
            'warranty_months' => 12,
        ]);

        $gr = app(GoodsReceiptService::class)->receive($gr->refresh());

        $this->assertSame('received', $gr->status);
        $this->assertSame(75_000.0, (float) Asset::withoutGlobalScopes()->first()->unit_price);
    }

    public function test_pembatalan_menghapus_unit_dan_batch_lalu_mengembalikan_status_pesanan(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'Latitude 7430'), 2);
        $gr = app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-6001', 'PF-6002']));

        $this->assertSame('completed', $po->refresh()->status);

        $gr = app(GoodsReceiptService::class)->cancel($gr, 'Barang salah kirim.');

        $this->assertSame('cancelled', $gr->status);
        $this->assertSame(0, Asset::withoutGlobalScopes()->count());
        $this->assertSame(0, PurchaseBatch::count());
        $this->assertSame('approved', $po->refresh()->status);
    }

    public function test_pembatalan_ditolak_bila_unitnya_sudah_diserahkan(): void
    {
        $po = $this->poDisetujui($this->barang('LAP', 'Latitude 7440'), 1);
        $gr = app(GoodsReceiptService::class)->receive($this->gr($po, ['PF-7001']));

        app(HandoverService::class)->issue(
            $this->draf(Asset::withoutGlobalScopes()->first(), $this->karyawan())
        );

        $this->expectExceptionMessage('sudah dipakai');

        app(GoodsReceiptService::class)->cancel($gr, 'Berubah pikiran.');
    }

    public function test_penerimaan_kosong_tidak_bisa_disetujui(): void
    {
        $gr = GoodsReceipt::create([
            'branch_id' => $this->cabang()->id,
            'receipt_date' => now(),
            'status' => 'draft',
        ]);

        $this->expectExceptionMessage('belum berisi unit');

        app(GoodsReceiptService::class)->receive($gr);
    }
}
