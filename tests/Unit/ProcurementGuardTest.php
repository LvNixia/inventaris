<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AttachmentType;
use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DataCheckService;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\VendorPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Penjagaan alur pengadaan yang sebelumnya bocor.
 *
 * Setiap pengujian di berkas ini menutup satu cacat nyata yang ditemukan saat
 * penelusuran kode: jalur-jalur yang dulu tidak punya tes sama sekali.
 */
class ProcurementGuardTest extends TestCase
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
     * Penerimaan tanpa pesanan yang sudah disetujui.
     */
    protected function penerimaan(?Product $produk = null, int $qty = 1): GoodsReceipt
    {
        $produk ??= $this->barang('ACC', 'Barang '.uniqid());

        $gr = GoodsReceipt::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        for ($i = 0; $i < $qty; $i++) {
            $gr->items()->create(['product_id' => $produk->id, 'unit_price' => 1_000_000]);
        }

        return app(GoodsReceiptService::class)->receive($gr->refresh());
    }

    /**
     * Pesanan yang sudah disetujui, beserta baris pertamanya.
     *
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem}
     */
    protected function pesananDisetujui(int $qty = 2): array
    {
        $po = PurchaseOrder::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'po_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $item = $po->items()->create([
            'product_id' => $this->barang('LAP', 'Laptop '.uniqid())->id,
            'quantity' => $qty,
            'unit_price' => 10_000_000,
        ]);

        $svc = app(PurchaseOrderService::class);
        $po = $svc->submit($po->refresh());

        Auth::login($this->adminKedua());
        $po = $svc->approve($po);
        Auth::login($this->admin);

        return [$po, $item];
    }

    /**
     * Dulu: lampiran menahan penghapusan aset lewat foreign key, sehingga
     * pembatalan berhenti dengan galat basis data mentah.
     */
    public function test_pembatalan_penerimaan_ditolak_rapi_bila_unitnya_punya_lampiran(): void
    {
        $gr = $this->penerimaan();
        $unit = Asset::withoutGlobalScopes()->firstOrFail();

        AssetAttachment::create([
            'asset_id' => $unit->id,
            'attachment_type_id' => AttachmentType::where('code', 'photo')->value('id'),
            'path' => 'asset-attachments/uji.png',
            'original_name' => 'uji.png',
            'mime' => 'image/png',
            'size' => 10,
        ]);

        try {
            app(GoodsReceiptService::class)->cancel($gr, 'salah kirim');
            $this->fail('Pembatalan seharusnya ditolak.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('sudah dipakai', $e->getMessage());
            $this->assertStringContainsString($unit->asset_code, $e->getMessage());
        }

        // Ditolak sebelum menyentuh apa pun: unitnya harus tetap utuh.
        $this->assertSame(1, Asset::withoutGlobalScopes()->count());
        $this->assertSame('received', $gr->refresh()->status);
    }

    /**
     * Dulu: melepas penerimaan dari faktur diabaikan diam-diam.
     */
    public function test_melepas_penerimaan_dari_faktur_benar_benar_memutus_tautan(): void
    {
        $gr1 = $this->penerimaan();
        $gr2 = $this->penerimaan();

        $svc = app(PurchaseInvoiceService::class);
        $faktur = $svc->create([
            'invoice_number' => 'INV/UJI/001',
            'vendor_id' => $this->vendor()->id,
            'branch_id' => $this->cabang()->id,
            'payment_term_id' => PaymentTerm::where('code', 'net30')->value('id'),
            'invoice_date' => now(),
            'total_amount' => 2_000_000,
        ], [$gr1->id, $gr2->id]);

        $this->assertSame(2, $faktur->receipts()->count());

        $svc->tautkanPenerimaan($faktur, [$gr1->id]);

        $this->assertSame(1, $faktur->refresh()->receipts()->count());

        // Nomor faktur ikut lepas dari unit penerimaan yang dicabut.
        $unitDilepas = Asset::withoutGlobalScopes()
            ->whereIn('id', $gr2->items()->pluck('asset_id'))
            ->first();
        $unitTetap = Asset::withoutGlobalScopes()
            ->whereIn('id', $gr1->items()->pluck('asset_id'))
            ->first();

        $this->assertNull($unitDilepas->invoice_number);
        $this->assertSame('INV/UJI/001', $unitTetap->invoice_number);
    }

    /**
     * Dulu: nomor faktur yang sudah dibatalkan tetap menempel pada unit aset,
     * sehingga laporan dan ekspor menyebut nomor yang tidak berlaku.
     */
    public function test_pembatalan_faktur_menarik_kembali_nomornya_dari_unit(): void
    {
        $gr = $this->penerimaan();

        $svc = app(PurchaseInvoiceService::class);
        $faktur = $svc->create([
            'invoice_number' => 'INV/UJI/002',
            'vendor_id' => $this->vendor()->id,
            'branch_id' => $this->cabang()->id,
            'invoice_date' => now(),
            'total_amount' => 1_000_000,
        ], [$gr->id]);

        $this->assertSame('INV/UJI/002', Asset::withoutGlobalScopes()->first()->invoice_number);

        $svc->cancel($faktur, 'salah tagih');

        $this->assertSame('cancelled', $faktur->refresh()->status);
        $this->assertNull(Asset::withoutGlobalScopes()->first()->invoice_number);
    }

    /**
     * Dulu: penerimaan draf tetap bisa disetujui setelah pesanannya dibatalkan,
     * melahirkan unit atas pesanan yang sudah tidak berlaku.
     */
    public function test_penerimaan_atas_pesanan_batal_ditolak(): void
    {
        [$po, $item] = $this->pesananDisetujui();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => now(),
            'status' => 'draft',
        ]);
        $gr->items()->create([
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'serial_number' => 'PF-BATAL-1',
            'unit_price' => 10_000_000,
        ]);

        // Pesanan dipaksa batal langsung ke basis data, meniru keadaan lama
        // ketika pembatalan belum memeriksa penerimaan yang menggantung.
        DB::table('purchase_orders')->where('id', $po->id)->update(['status' => 'cancelled']);

        $this->expectExceptionMessage('sudah dibatalkan, jadi barangnya tidak bisa diterima');

        app(GoodsReceiptService::class)->receive($gr->refresh());
    }

    /**
     * Dulu: pesanan bisa dibatalkan walau masih menyisakan penerimaan draf.
     */
    public function test_pesanan_dengan_penerimaan_menggantung_tidak_bisa_dibatalkan(): void
    {
        [$po, $item] = $this->pesananDisetujui();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'receipt_date' => now(),
            'status' => 'draft',
        ]);
        $gr->items()->create([
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'serial_number' => 'PF-GANTUNG-1',
        ]);

        $this->expectExceptionMessage('penerimaan barang yang belum dibatalkan');

        app(PurchaseOrderService::class)->cancel($po, 'vendor mundur');
    }

    public function test_pesanan_yang_penerimaannya_sudah_dibatalkan_boleh_dibatalkan(): void
    {
        [$po, $item] = $this->pesananDisetujui();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => now(),
            'status' => 'draft',
        ]);
        $gr->items()->create([
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'serial_number' => 'PF-LEPAS-1',
            'unit_price' => 10_000_000,
        ]);

        $grSvc = app(GoodsReceiptService::class);
        $gr = $grSvc->receive($gr->refresh());
        $grSvc->cancel($gr, 'salah kirim');

        $po = app(PurchaseOrderService::class)->cancel($po->refresh(), 'vendor mundur');

        $this->assertSame('cancelled', $po->status);
    }

    /**
     * Faktur dikunci sebelum sisanya diperiksa, jadi alokasi kedua pada
     * transaksi yang sama tetap melihat sisa yang sudah berkurang.
     */
    public function test_alokasi_berturut_turut_tidak_bisa_melebihi_sisa(): void
    {
        $svc = app(PurchaseInvoiceService::class);
        $faktur = $svc->create([
            'invoice_number' => 'INV/UJI/003',
            'vendor_id' => $this->vendor()->id,
            'branch_id' => $this->cabang()->id,
            'invoice_date' => now(),
            'total_amount' => 1_000_000,
        ], []);

        $bayar = fn (float $nilai) => app(VendorPaymentService::class)->pay([
            'vendor_id' => $this->vendor()->id,
            'branch_id' => $this->cabang()->id,
            'payment_date' => now(),
            'amount' => $nilai,
            'payment_method' => 'transfer',
        ], [['purchase_invoice_id' => $faktur->id, 'amount' => $nilai]]);

        $bayar(700_000);

        $this->expectExceptionMessage('melebihi sisa tagihannya');

        $bayar(400_000);
    }

    /**
     * Pemeriksaan hutang dulu menembak satu kueri per faktur.
     */
    public function test_pemeriksaan_data_tidak_menembak_kueri_per_faktur(): void
    {
        $svc = app(PurchaseInvoiceService::class);

        foreach (range(1, 6) as $i) {
            $svc->create([
                'invoice_number' => 'INV/N1/'.$i,
                'vendor_id' => $this->vendor()->id,
                'branch_id' => $this->cabang()->id,
                'invoice_date' => now(),
                'total_amount' => 1_000_000,
            ], []);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(DataCheckService::class)->runChecks();

        $jumlahKueri = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Enam faktur tidak boleh menambah enam kueri; jumlahnya harus tetap
        // sekitar banyaknya pemeriksaan, bukan banyaknya baris.
        $this->assertLessThan(15, $jumlahKueri, "Pemeriksaan Data menjalankan {$jumlahKueri} kueri; pola N+1 kembali.");
    }
}
