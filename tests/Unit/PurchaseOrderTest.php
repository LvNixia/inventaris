<?php

namespace Tests\Unit;

use App\Models\PaymentTerm;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Alur pesanan pembelian: draf, pengajuan, persetujuan, pembatalan.
 */
class PurchaseOrderTest extends TestCase
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
     * @param  array<int, array{qty: int, harga: float, ppn?: float}>  $baris
     */
    protected function po(array $baris = [['qty' => 2, 'harga' => 1000000]]): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'payment_term_id' => $this->vendor()->payment_term_id,
            'po_date' => now(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($baris as $i => $b) {
            $po->items()->create([
                // Model dibuat unik: products punya kendala unik pada
                // kategori + merek + model.
                'product_id' => $this->barang('LAP', 'Model '.$i.'-'.uniqid())->id,
                'quantity' => $b['qty'],
                'unit_price' => $b['harga'],
                'tax_percent' => $b['ppn'] ?? 0,
            ]);
        }

        return $po->refresh();
    }

    /**
     * Pengaju dan penyetuju harus orang berbeda, jadi tesnya butuh dua akun.
     */
    protected function adminKedua(): User
    {
        $u = new User;
        $u->name = 'Admin Kedua';
        $u->email = 'kedua@contoh.test';
        $u->password = 'rahasia-uji';
        $u->role = 'admin_pusat';
        $u->is_active = true;
        $u->save();

        return $u;
    }

    public function test_nilai_po_dihitung_dari_barisnya_termasuk_pajak(): void
    {
        $po = $this->po([
            ['qty' => 2, 'harga' => 1_000_000, 'ppn' => 11],
            ['qty' => 3, 'harga' => 500_000],
        ]);

        app(PurchaseOrderService::class)->recalculate($po);
        $po->refresh();

        // 2.000.000 + 1.500.000 = 3.500.000; PPN hanya pada baris pertama.
        $this->assertSame('3500000.00', $po->subtotal);
        $this->assertSame('220000.00', $po->tax);
        $this->assertSame('3720000.00', $po->total);
    }

    public function test_pengajuan_mengunci_isi_po(): void
    {
        $po = app(PurchaseOrderService::class)->submit($this->po());

        $this->assertSame('pending_approval', $po->status);
        $this->assertNotNull($po->submitted_at);
        $this->assertFalse($po->isEditable());
    }

    public function test_po_kosong_tidak_bisa_diajukan(): void
    {
        $po = PurchaseOrder::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'po_date' => now(),
            'status' => 'draft',
        ]);

        $this->expectExceptionMessage('belum berisi barang');

        app(PurchaseOrderService::class)->submit($po);
    }

    public function test_nomor_po_baru_terbit_saat_disetujui(): void
    {
        $po = app(PurchaseOrderService::class)->submit($this->po());

        $this->assertNull($po->po_number);

        Auth::login($this->adminKedua());
        $po = app(PurchaseOrderService::class)->approve($po);

        $this->assertSame('approved', $po->status);
        $this->assertNotNull($po->po_number);
        $this->assertStringStartsWith('PO/JKT/', $po->po_number);
        $this->assertNotNull($po->approved_at);
    }

    /**
     * Seri penomoran dipisah agar PO tidak menggeser urutan surat serah terima.
     */
    public function test_nomor_po_berurutan_per_cabang_per_bulan(): void
    {
        $satu = $this->poDiajukanOrangLain();
        $dua = $this->poDiajukanOrangLain();

        Auth::login($this->adminKedua());

        $pertama = app(PurchaseOrderService::class)->approve($satu);
        $kedua = app(PurchaseOrderService::class)->approve($dua);

        $this->assertStringEndsWith('/01', $pertama->po_number);
        $this->assertStringEndsWith('/02', $kedua->po_number);
    }

    /**
     * Pesanan besar: pemeriksaan orang kedua tetap berlaku.
     */
    public function test_pengaju_tidak_bisa_menyetujui_po_besarnya_sendiri(): void
    {
        $po = app(PurchaseOrderService::class)->submit($this->poBesar());

        $this->expectExceptionMessage('harus disetujui orang lain');

        app(PurchaseOrderService::class)->approve($po);
    }

    public function test_admin_cabang_tidak_bisa_menyetujui_po_besar(): void
    {
        $po = app(PurchaseOrderService::class)->submit($this->poBesar());

        $this->admin->update(['role' => 'admin_cabang']);
        Auth::login($this->admin->refresh());

        $this->expectExceptionMessage('hanya bisa disetujui Admin Pusat');

        app(PurchaseOrderService::class)->approve($po);
    }

    /**
     * Belanja kecil tidak perlu menunggu orang lain.
     *
     * Menahannya tidak menghasilkan kontrol apa pun — hanya mendorong akun
     * penyetuju dipinjam, dan jejaknya justru hilang.
     */
    public function test_po_kecil_boleh_disetujui_pengajunya_sendiri(): void
    {
        config(['pengadaan.batas_persetujuan_mandiri' => 5_000_000]);

        $po = app(PurchaseOrderService::class)->submit(
            $this->po([['qty' => 2, 'harga' => 350_000, 'ppn' => 0]])
        );

        $po = app(PurchaseOrderService::class)->approve($po);

        $this->assertSame('approved', $po->status);
        $this->assertSame($this->admin->id, $po->approved_by);
        $this->assertNotNull($po->po_number);
    }

    /**
     * Batas nol berarti seluruh pesanan wajib disetujui orang lain.
     */
    public function test_batas_nol_mewajibkan_persetujuan_orang_lain(): void
    {
        config(['pengadaan.batas_persetujuan_mandiri' => 0]);

        $po = app(PurchaseOrderService::class)->submit(
            $this->po([['qty' => 1, 'harga' => 150_000, 'ppn' => 0]])
        );

        $this->expectExceptionMessage('harus disetujui orang lain');

        app(PurchaseOrderService::class)->approve($po);
    }

    /**
     * Pesanan yang nilainya melewati batas persetujuan mandiri.
     */
    protected function poBesar(): PurchaseOrder
    {
        return $this->po([['qty' => 2, 'harga' => 18_500_000]]);
    }

    public function test_po_draf_tidak_bisa_langsung_disetujui(): void
    {
        $po = $this->po();

        $this->expectExceptionMessage('menunggu persetujuan');

        app(PurchaseOrderService::class)->approve($po);
    }

    public function test_pengembalian_ke_draf_membuka_kunci_isi(): void
    {
        $po = app(PurchaseOrderService::class)->submit($this->po());

        $po = app(PurchaseOrderService::class)->returnToDraft($po, 'Harga belum dinegosiasi.');

        $this->assertSame('draft', $po->status);
        $this->assertNull($po->submitted_at);
        $this->assertTrue($po->isEditable());
        $this->assertStringContainsString('Harga belum dinegosiasi', $po->notes);
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $this->expectExceptionMessage('Alasan wajib diisi');

        app(PurchaseOrderService::class)->cancel($this->po(), '  ');
    }

    public function test_po_yang_sudah_dibatalkan_tidak_bisa_dibatalkan_lagi(): void
    {
        $po = app(PurchaseOrderService::class)->cancel($this->po(), 'Vendor tidak sanggup.');

        $this->assertSame('cancelled', $po->status);

        $this->expectExceptionMessage('sudah dibatalkan');

        app(PurchaseOrderService::class)->cancel($po, 'Sekali lagi.');
    }

    public function test_po_dengan_penerimaan_sebagian_tidak_bisa_dibatalkan(): void
    {
        $po = $this->po();
        $po->update(['status' => 'partial_receipt']);

        $this->expectExceptionMessage('batalkan penerimaannya terlebih dahulu');

        app(PurchaseOrderService::class)->cancel($po->refresh(), 'Berubah pikiran.');
    }

    /**
     * PO yang sudah diajukan oleh admin pertama, siap disetujui orang lain.
     */
    protected function poDiajukanOrangLain(): PurchaseOrder
    {
        Auth::login($this->admin);

        return app(PurchaseOrderService::class)->submit($this->po());
    }
}
