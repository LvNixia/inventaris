<?php

namespace Tests\Feature;

use App\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\VendorPayments\Pages\CreateVendorPayment;
use App\Models\Asset;
use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseOrderService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Formulir rantai pengadaan: pesanan → penerimaan → faktur.
 *
 * Aturan bisnisnya sudah diuji lewat service. Yang diuji di sini adalah hal
 * yang hanya muncul lewat antarmuka: bidang yang ikut tersimpan meski
 * tersembunyi, pilihan yang tetap terbaca saat dokumen dibuka kembali, dan
 * penarikan data antar dokumen.
 */
class ProcurementFormTest extends TestCase
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

    protected function produk(): Product
    {
        return $this->barang('LAP', 'Uji-'.uniqid());
    }

    /**
     * Pesanan yang sudah disetujui, satu baris berisi $qty unit.
     */
    protected function poDisetujui(
        Product $produk,
        int $qty = 3,
        float $harga = 10_000_000,
        float $persenPajak = 0,
        ?int $syaratId = null,
    ): PurchaseOrder {
        $po = PurchaseOrder::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => $this->vendor()->id,
            'payment_term_id' => $syaratId,
            'po_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $po->items()->create([
            'product_id' => $produk->id,
            'quantity' => $qty,
            'unit_price' => $harga,
            'tax_percent' => $persenPajak,
            'warranty_months' => 12,
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
     * Penerimaan yang sudah disetujui atas seluruh isi pesanan.
     */
    protected function penerimaanDisetujui(PurchaseOrder $po, array $serial): GoodsReceipt
    {
        $poItem = $po->items->first();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        foreach ($serial as $nomor) {
            $gr->items()->create([
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'serial_number' => $nomor,
                'unit_price' => $poItem->unit_price,
                'warranty_months' => $poItem->warranty_months,
            ]);
        }

        return app(GoodsReceiptService::class)->receive($gr);
    }

    /**
     * Kolom barang disembunyikan saat penerimaan berasal dari pesanan, tetapi
     * nilainya wajib tetap ikut tersimpan. Tanpa itu penyimpanan gagal di
     * basis data karena `product_id` tidak boleh kosong.
     */
    public function test_barang_tetap_tersimpan_meski_kolomnya_tersembunyi(): void
    {
        $po = $this->poDisetujui($this->produk(), 2);
        $poItem = $po->items->first();

        Livewire::test(CreateGoodsReceipt::class)
            ->fillForm([
                'purchase_order_id' => $po->id,
                'branch_id' => $po->branch_id,
                'vendor_id' => $po->vendor_id,
                'receipt_date' => now()->toDateString(),
                'items' => [
                    ['purchase_order_item_id' => $poItem->id, 'serial_number' => 'SN-FORM-1'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = GoodsReceipt::withoutGlobalScopes()->latest('id')->first()->items->first();

        $this->assertSame($poItem->product_id, $item->product_id);
        $this->assertEquals($poItem->unit_price, $item->unit_price);
        $this->assertSame($poItem->warranty_months, $item->warranty_months);
    }

    /**
     * Satu baris formulir per unit pesanan yang belum diterima, lengkap dengan
     * harga dan garansinya.
     */
    public function test_sisa_pesanan_bisa_ditarik_menjadi_baris_penerimaan(): void
    {
        $po = $this->poDisetujui($this->produk(), 3);
        $poItem = $po->items->first();

        $baris = Livewire::test(CreateGoodsReceipt::class)
            ->fillForm(['purchase_order_id' => $po->id])
            ->callAction(TestAction::make('tarikDariPesanan')->schemaComponent('aksiPenerimaan'))
            ->get('data')['items'];

        $this->assertCount(3, $baris);

        foreach ($baris as $isi) {
            $this->assertSame($poItem->id, $isi['purchase_order_item_id']);
            $this->assertSame($poItem->product_id, $isi['product_id']);
            $this->assertEquals($poItem->unit_price, $isi['unit_price']);
            $this->assertNull($isi['serial_number']);
        }
    }

    /**
     * Unit yang sudah diterima tidak ditarik dua kali.
     */
    public function test_penarikan_hanya_mengambil_yang_belum_diterima(): void
    {
        $po = $this->poDisetujui($this->produk(), 3);
        $this->penerimaanDisetujui($po, ['SN-SISA-1']);

        $baris = Livewire::test(CreateGoodsReceipt::class)
            ->fillForm(['purchase_order_id' => $po->refresh()->id])
            ->callAction(TestAction::make('tarikDariPesanan')->schemaComponent('aksiPenerimaan'))
            ->get('data')['items'];

        $this->assertCount(2, $baris);
    }

    /**
     * Pesanan yang sudah lengkap diterima berubah status menjadi `completed`
     * sehingga tidak lagi masuk daftar pilihan. Penerimaan yang menunjuk
     * kepadanya tetap harus menampilkan nomor pesanannya saat dibuka kembali.
     */
    public function test_pesanan_tetap_terbaca_pada_penerimaan_lama(): void
    {
        $po = $this->poDisetujui($this->produk(), 1);
        $gr = $this->penerimaanDisetujui($po, ['SN-LAMA-1']);

        $this->assertSame('completed', $po->refresh()->status);

        Livewire::test(EditGoodsReceipt::class, ['record' => $gr->getRouteKey()])
            ->assertFormSet(['purchase_order_id' => $po->id])
            ->assertFormFieldExists(
                'purchase_order_id',
                checkFieldUsing: fn (Select $field): bool => array_key_exists(
                    $po->id,
                    $field->getOptions(),
                ),
            );
    }

    /**
     * Nilai tagihan ditarik dari penerimaan yang dipilih, dan penerimaan yang
     * sudah ditagih tidak ditawarkan lagi pada faktur berikutnya.
     */
    public function test_nilai_faktur_ditarik_dari_penerimaan(): void
    {
        $po = $this->poDisetujui($this->produk(), 2, 10_000_000);
        $gr = $this->penerimaanDisetujui($po, ['SN-INV-1', 'SN-INV-2']);

        $komponen = Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm([
                'vendor_id' => $this->vendor()->id,
                'invoice_number' => 'INV-UJI-FORM',
                'branch_id' => $gr->branch_id,
                'invoice_date' => now()->toDateString(),
                'goods_receipt_ids' => [$gr->id],
            ]);

        $komponen->assertFormSet([
            'subtotal' => 20_000_000,
            'total_amount' => 20_000_000,
        ]);

        $komponen->call('create')->assertHasNoFormErrors();

        $this->assertNotContains(
            $gr->id,
            GoodsReceipt::query()->belumDitagih()->pluck('id')->all(),
        );
    }

    /**
     * PPN diisi manual, tetapi totalnya tetap dihitung ulang agar tidak ada
     * faktur yang tersimpan dengan total tidak sesuai isinya.
     */
    public function test_ppn_menambah_total_tagihan(): void
    {
        $po = $this->poDisetujui($this->produk(), 1, 10_000_000);
        $gr = $this->penerimaanDisetujui($po, ['SN-PPN-1']);

        Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm([
                'vendor_id' => $this->vendor()->id,
                'invoice_number' => 'INV-UJI-PPN',
                'branch_id' => $gr->branch_id,
                'invoice_date' => now()->toDateString(),
                'goods_receipt_ids' => [$gr->id],
            ])
            ->fillForm(['tax' => 1_100_000])
            ->assertFormSet([
                'subtotal' => 10_000_000,
                'total_amount' => 11_100_000,
            ]);
    }

    /**
     * Pintasan dari daftar pesanan: halaman penerimaan terbuka dengan pesanan
     * terpilih dan sisa unitnya sudah menjadi baris.
     */
    public function test_penerimaan_terisi_dari_alamat_pesanan(): void
    {
        $po = $this->poDisetujui($this->produk(), 2);

        $data = Livewire::withQueryParams(['purchase_order_id' => $po->id])
            ->test(CreateGoodsReceipt::class)
            ->get('data');

        $this->assertSame($po->id, $data['purchase_order_id']);
        $this->assertSame($po->vendor_id, $data['vendor_id']);
        $this->assertCount(2, $data['items']);
    }

    /**
     * Pintasan dari daftar penerimaan: faktur terbuka dengan vendor, penerimaan,
     * dan nilainya sudah terisi.
     */
    public function test_faktur_terisi_dari_alamat_penerimaan(): void
    {
        $po = $this->poDisetujui($this->produk(), 2, 7_500_000);
        $gr = $this->penerimaanDisetujui($po, ['SN-URL-1', 'SN-URL-2']);

        $data = Livewire::withQueryParams(['goods_receipt_id' => $gr->id])
            ->test(CreatePurchaseInvoice::class)
            ->get('data');

        $this->assertSame($gr->vendor_id, $data['vendor_id']);
        $this->assertSame([$gr->id], $data['goods_receipt_ids']);
        $this->assertEquals(15_000_000, (float) $data['subtotal']);
        $this->assertEquals(15_000_000, (float) $data['total_amount']);
    }

    /**
     * Syarat pembayaran dan PPN sudah ada di pesanan, jadi faktur tidak boleh
     * meminta keduanya diketik lagi.
     */
    public function test_faktur_mewarisi_syarat_dan_ppn_dari_pesanan(): void
    {
        $syarat = PaymentTerm::where('code', 'net60')->value('id')
            ?? PaymentTerm::where('code', '!=', 'net30')->value('id');

        $po = $this->poDisetujui($this->produk(), 2, 10_000_000, 11, $syarat);
        $gr = $this->penerimaanDisetujui($po, ['SN-WARIS-1', 'SN-WARIS-2']);

        $data = Livewire::withQueryParams(['goods_receipt_id' => $gr->id])
            ->test(CreatePurchaseInvoice::class)
            ->get('data');

        $this->assertSame($syarat, $data['payment_term_id']);
        $this->assertSame($po->branch_id, $data['branch_id']);
        $this->assertEquals(20_000_000, (float) $data['subtotal']);
        $this->assertEquals(2_200_000, (float) $data['tax']);
        $this->assertEquals(22_200_000, (float) $data['total_amount']);
    }

    /**
     * Syarat pembayaran yang mengikat adalah yang tercatat di pesanan, bukan
     * syarat vendor yang berlaku hari ini.
     */
    public function test_syarat_pesanan_menang_atas_syarat_vendor_terkini(): void
    {
        $syaratPesanan = PaymentTerm::where('code', 'net60')->value('id')
            ?? PaymentTerm::where('code', '!=', 'net30')->value('id');

        $po = $this->poDisetujui($this->produk(), 1, 5_000_000, 0, $syaratPesanan);
        $gr = $this->penerimaanDisetujui($po, ['SN-SYARAT-1']);

        // Vendor mengubah syaratnya setelah pesanan berjalan.
        $lain = PaymentTerm::where('id', '!=', $syaratPesanan)->value('id');
        $this->vendor()->update(['payment_term_id' => $lain]);

        $data = Livewire::withQueryParams(['goods_receipt_id' => $gr->id])
            ->test(CreatePurchaseInvoice::class)
            ->get('data');

        $this->assertSame($syaratPesanan, $data['payment_term_id']);
    }

    /**
     * Pembayaran dibuka dari daftar faktur: vendor, cabang, alokasi, dan
     * nilainya sudah terisi sebesar sisa tagihan.
     */
    public function test_pembayaran_terisi_dari_alamat_faktur(): void
    {
        $po = $this->poDisetujui($this->produk(), 1, 9_000_000);
        $gr = $this->penerimaanDisetujui($po, ['SN-BAYAR-1']);

        $faktur = app(PurchaseInvoiceService::class)->create([
            'vendor_id' => $gr->vendor_id,
            'branch_id' => $gr->branch_id,
            'invoice_number' => 'INV-UJI-BAYAR',
            'invoice_date' => now()->toDateString(),
            'subtotal' => 9_000_000,
            'tax' => 0,
            'total_amount' => 9_000_000,
        ], [$gr->id]);

        $data = Livewire::withQueryParams(['purchase_invoice_id' => $faktur->id])
            ->test(CreateVendorPayment::class)
            ->get('data');

        $this->assertSame($faktur->vendor_id, $data['vendor_id']);
        $this->assertSame($faktur->branch_id, $data['branch_id']);
        $this->assertEquals(9_000_000, (float) $data['amount']);

        $alokasi = array_values($data['allocations']);
        $this->assertCount(1, $alokasi);
        $this->assertSame($faktur->id, $alokasi[0]['purchase_invoice_id']);
        $this->assertEquals(9_000_000, (float) $alokasi[0]['amount']);
    }

    /**
     * Nilai pembayaran mengikuti jumlah alokasinya, tanpa diketik ulang.
     */
    public function test_nilai_pembayaran_mengikuti_alokasi(): void
    {
        $faktur = collect([['INV-JML-1', 4_000_000], ['INV-JML-2', 6_000_000]])
            ->map(function (array $isi): int {
                [$nomor, $nilai] = $isi;

                $po = $this->poDisetujui($this->produk(), 1, $nilai);
                $gr = $this->penerimaanDisetujui($po, ['SN-'.$nomor]);

                return app(PurchaseInvoiceService::class)->create([
                    'vendor_id' => $gr->vendor_id,
                    'branch_id' => $gr->branch_id,
                    'invoice_number' => $nomor,
                    'invoice_date' => now()->toDateString(),
                    'subtotal' => $nilai,
                    'tax' => 0,
                    'total_amount' => $nilai,
                ], [$gr->id])->id;
            });

        $data = Livewire::test(CreateVendorPayment::class)
            ->fillForm([
                'vendor_id' => $this->vendor()->id,
                'branch_id' => $this->cabang()->id,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'transfer',
                'allocations' => [
                    ['purchase_invoice_id' => $faktur[0], 'amount' => 4_000_000],
                    ['purchase_invoice_id' => $faktur[1], 'amount' => 6_000_000],
                ],
            ])
            ->get('data');

        $this->assertEquals(10_000_000, (float) $data['amount']);
    }

    /**
     * Belanja marketplace: pesanan disetujui tanpa vendor, tokonya baru
     * ditentukan saat barangnya diterima, dan nomor pesanannya menempel pada
     * unit asetnya sebagai bukti beli — tanpa faktur sama sekali.
     */
    public function test_pengadaan_tanpa_vendor_dan_tanpa_faktur(): void
    {
        $produk = $this->produk();

        $po = PurchaseOrder::create([
            'branch_id' => $this->cabang()->id,
            'vendor_id' => null,
            'po_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $po->items()->create([
            'product_id' => $produk->id,
            'quantity' => 1,
            'unit_price' => 8_000_000,
            'warranty_months' => 12,
        ]);

        $svc = app(PurchaseOrderService::class);
        $po = $svc->submit($po->refresh());
        Auth::login($this->adminKedua());
        $po = $svc->approve($po);
        Auth::login($this->admin);

        $this->assertNull($po->vendor_id);

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $this->vendor()->id,
            'receipt_date' => now(),
            'purchase_reference' => 'INV/20260910/MPL/8891234',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $gr->items()->create([
            'purchase_order_item_id' => $po->items->first()->id,
            'product_id' => $produk->id,
            'serial_number' => 'SN-MPL-1',
            'unit_price' => 8_000_000,
            'warranty_months' => 12,
        ]);

        $gr = app(GoodsReceiptService::class)->receive($gr);

        $unit = Asset::withoutGlobalScopes()->findOrFail($gr->items->first()->asset_id);

        $this->assertSame('INV/20260910/MPL/8891234', $unit->invoice_number);
        $this->assertSame($this->vendor()->id, $unit->purchaseBatch->vendor_id);
    }

    /**
     * Harga marketplace bergerak, jadi selisihnya diberitahukan — bukan
     * ditolak.
     */
    public function test_selisih_harga_diperingatkan_bukan_ditolak(): void
    {
        $po = $this->poDisetujui($this->produk(), 1, 10_000_000);
        $poItem = $po->items->first();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $gr->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'serial_number' => 'SN-NAIK-1',
            'unit_price' => 12_000_000,
            'warranty_months' => 12,
        ]);

        $peringatan = $gr->refresh()->peringatanSelisihHarga();

        $this->assertNotNull($peringatan);
        $this->assertStringContainsString('naik', $peringatan);
        $this->assertStringContainsString('+20,0%', $peringatan);

        // Tetap boleh disetujui: selisih harga bukan alasan menolak barang.
        $gr = app(GoodsReceiptService::class)->receive($gr);

        $this->assertSame('received', $gr->status);
    }

    /**
     * Pergerakan harga kecil tidak perlu diperingatkan.
     */
    public function test_selisih_harga_kecil_tidak_diperingatkan(): void
    {
        $po = $this->poDisetujui($this->produk(), 1, 10_000_000);
        $poItem = $po->items->first();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => now(),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
        $gr->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'serial_number' => 'SN-TIPIS-1',
            'unit_price' => 10_400_000,
            'warranty_months' => 12,
        ]);

        $this->assertNull($gr->refresh()->peringatanSelisihHarga());
    }
}
