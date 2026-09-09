<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseInvoiceService;
use App\Services\VendorPaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Tagihan vendor dan pembayarannya.
 */
class PayableTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->siapkanDataAcuan();
    }

    protected function vendor(string $nama = 'Sinar Terang Komputer'): Vendor
    {
        return Vendor::firstOrCreate(['name' => $nama], [
            'type' => 'toko',
            'payment_term_id' => PaymentTerm::where('code', 'net30')->value('id'),
            'is_active' => true,
        ]);
    }

    /**
     * Penerimaan tanpa pesanan, berisi $qty unit seharga $harga.
     */
    protected function penerimaan(int $qty = 2, float $harga = 1_000_000, ?Vendor $vendor = null): GoodsReceipt
    {
        $vendor ??= $this->vendor();
        $produk = $this->barang('ACC', 'Barang '.uniqid());

        $gr = GoodsReceipt::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $vendor->id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        for ($i = 0; $i < $qty; $i++) {
            $gr->items()->create([
                'product_id' => $produk->id,
                'unit_price' => $harga,
            ]);
        }

        return app(GoodsReceiptService::class)->receive($gr->refresh());
    }

    /**
     * @param  array<int, int>  $grIds
     */
    protected function faktur(float $total, array $grIds = [], ?Vendor $vendor = null, ?string $nomor = null): PurchaseInvoice
    {
        $vendor ??= $this->vendor();

        return app(PurchaseInvoiceService::class)->create([
            'invoice_number' => $nomor ?? 'INV-'.uniqid(),
            'vendor_id' => $vendor->id,
            'branch_id' => $this->cabang()->id,
            'payment_term_id' => $vendor->payment_term_id,
            'invoice_date' => now(),
            'total_amount' => $total,
        ], $grIds);
    }

    protected function bayar(PurchaseInvoice|array $alokasi, ?float $nilai = null, ?Vendor $vendor = null): VendorPayment
    {
        $vendor ??= $this->vendor();

        if ($alokasi instanceof PurchaseInvoice) {
            $nilai ??= (float) $alokasi->total_amount;
            $alokasi = [['purchase_invoice_id' => $alokasi->id, 'amount' => $nilai]];
        }

        $nilai ??= array_sum(array_column($alokasi, 'amount'));

        return app(VendorPaymentService::class)->pay([
            'vendor_id' => $vendor->id,
            'branch_id' => $this->cabang()->id,
            'payment_date' => now(),
            'amount' => $nilai,
            'payment_method' => 'transfer',
        ], $alokasi);
    }

    public function test_jatuh_tempo_dihitung_dari_syarat_pembayaran(): void
    {
        $faktur = $this->faktur(5_000_000);

        $this->assertSame(now()->addDays(30)->toDateString(), $faktur->due_date->toDateString());
    }

    public function test_nomor_faktur_boleh_sama_antar_vendor(): void
    {
        $this->faktur(1_000_000, [], $this->vendor('Vendor A'), 'INV/001');
        $this->faktur(1_000_000, [], $this->vendor('Vendor B'), 'INV/001');

        $this->assertSame(2, PurchaseInvoice::count());
    }

    public function test_nomor_faktur_kembar_pada_vendor_yang_sama_ditolak(): void
    {
        $this->faktur(1_000_000, [], null, 'INV/001');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->faktur(1_000_000, [], null, 'INV/001');
    }

    /**
     * K4: satu faktur boleh mencakup beberapa penerimaan.
     */
    public function test_satu_faktur_mencakup_beberapa_penerimaan(): void
    {
        $gr1 = $this->penerimaan(2, 1_000_000);
        $gr2 = $this->penerimaan(3, 500_000);

        $faktur = $this->faktur(3_500_000, [$gr1->id, $gr2->id]);

        $this->assertSame(2, $faktur->receipts()->count());
        $this->assertSame(2, $faktur->goodsReceipts()->count());
    }

    /**
     * Nomor faktur menetes ke batch pembelian, sehingga Asset::invoice_number
     * yang dipakai laporan dan ekspor ikut terisi.
     */
    public function test_nomor_faktur_tersalin_ke_unit_aset(): void
    {
        $gr = $this->penerimaan(2, 1_000_000);

        $this->assertNull(Asset::withoutGlobalScopes()->first()->invoice_number);

        $this->faktur(2_000_000, [$gr->id], null, 'INV/2026/09/007');

        $this->assertSame('INV/2026/09/007', Asset::withoutGlobalScopes()->first()->invoice_number);
    }

    public function test_penerimaan_yang_belum_disetujui_tidak_bisa_ditagihkan(): void
    {
        $gr = GoodsReceipt::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'receipt_date' => now(),
            'status' => 'draft',
        ]);

        $this->expectExceptionMessage('belum disetujui');

        $this->faktur(1_000_000, [$gr->id]);
    }

    public function test_penerimaan_vendor_lain_tidak_bisa_ditagihkan(): void
    {
        $gr = $this->penerimaan(1, 500_000, $this->vendor('Vendor Lain'));

        $this->expectExceptionMessage('vendor lain');

        $this->faktur(500_000, [$gr->id]);
    }

    public function test_pembayaran_penuh_melunasi_faktur(): void
    {
        $faktur = $this->faktur(5_000_000);

        $this->bayar($faktur);

        $faktur->refresh();
        $this->assertSame('paid', $faktur->status);
        $this->assertSame(5_000_000.0, (float) $faktur->paid_amount);
        $this->assertSame(0.0, $faktur->outstanding);
    }

    public function test_pembayaran_sebagian_menandai_partial(): void
    {
        $faktur = $this->faktur(5_000_000);

        $this->bayar($faktur, 2_000_000);

        $faktur->refresh();
        $this->assertSame('partial', $faktur->status);
        $this->assertSame(3_000_000.0, $faktur->outstanding);

        // Pelunasan sisanya menutup faktur.
        $this->bayar($faktur, 3_000_000);

        $this->assertSame('paid', $faktur->refresh()->status);
    }

    /**
     * K5: satu transfer melunasi beberapa faktur sekaligus.
     */
    public function test_satu_pembayaran_melunasi_beberapa_faktur(): void
    {
        $a = $this->faktur(3_000_000);
        $b = $this->faktur(2_000_000);

        $pembayaran = $this->bayar([
            ['purchase_invoice_id' => $a->id, 'amount' => 3_000_000],
            ['purchase_invoice_id' => $b->id, 'amount' => 2_000_000],
        ], 5_000_000);

        $this->assertStringStartsWith('PAY/JKT/', $pembayaran->payment_number);
        $this->assertSame(2, $pembayaran->allocations()->count());
        $this->assertSame('paid', $a->refresh()->status);
        $this->assertSame('paid', $b->refresh()->status);
    }

    public function test_jumlah_alokasi_harus_sama_dengan_nilai_pembayaran(): void
    {
        $faktur = $this->faktur(5_000_000);

        $this->expectExceptionMessage('tidak sama dengan nilai pembayaran');

        $this->bayar([['purchase_invoice_id' => $faktur->id, 'amount' => 2_000_000]], 5_000_000);
    }

    public function test_alokasi_melebihi_sisa_tagihan_ditolak(): void
    {
        $faktur = $this->faktur(1_000_000);

        $this->expectExceptionMessage('melebihi sisa tagihannya');

        $this->bayar($faktur, 1_500_000);
    }

    public function test_alokasi_melebihi_sisa_setelah_pembayaran_sebagian_ditolak(): void
    {
        $faktur = $this->faktur(1_000_000);
        $this->bayar($faktur, 600_000);

        $this->expectExceptionMessage('melebihi sisa tagihannya');

        $this->bayar($faktur->refresh(), 500_000);
    }

    public function test_faktur_dibatalkan_tidak_bisa_dibayar(): void
    {
        $faktur = $this->faktur(1_000_000);
        app(PurchaseInvoiceService::class)->cancel($faktur, 'Salah tagih.');

        $this->expectExceptionMessage('sudah dibatalkan dan tidak bisa dibayar');

        $this->bayar($faktur->refresh(), 1_000_000);
    }

    public function test_faktur_yang_sudah_dibayar_tidak_bisa_dibatalkan(): void
    {
        $faktur = $this->faktur(1_000_000);
        $this->bayar($faktur);

        $this->expectExceptionMessage('batalkan pembayarannya terlebih dahulu');

        app(PurchaseInvoiceService::class)->cancel($faktur->refresh(), 'Berubah pikiran.');
    }

    public function test_pembatalan_pembayaran_mengembalikan_status_faktur(): void
    {
        $faktur = $this->faktur(5_000_000);
        $pembayaran = $this->bayar($faktur);

        $this->assertSame('paid', $faktur->refresh()->status);

        app(VendorPaymentService::class)->cancel($pembayaran, 'Transfer gagal.');

        $faktur->refresh();
        $this->assertSame('unpaid', $faktur->status);
        $this->assertSame(0.0, (float) $faktur->paid_amount);
        // Ditandai batal, bukan dihapus, agar nomornya tidak dipakai ulang.
        $this->assertNotNull($pembayaran->refresh()->cancelled_at);
        $this->assertSame(1, VendorPayment::withoutGlobalScopes()->count());
    }

    public function test_pembayaran_tanpa_alokasi_ditolak(): void
    {
        $this->expectExceptionMessage('belum dialokasikan');

        $this->bayar([], 1_000_000);
    }

    public function test_faktur_lewat_jatuh_tempo_terdeteksi(): void
    {
        $faktur = $this->faktur(1_000_000);
        $faktur->update(['due_date' => now()->subDays(5)]);

        $this->assertTrue($faktur->refresh()->isOverdue());
        $this->assertSame(5, $faktur->age_in_days);
        $this->assertSame(1, PurchaseInvoice::query()->overdue()->count());
    }
}
