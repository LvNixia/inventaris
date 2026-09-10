<?php

namespace Tests\Feature;

use App\Filament\Resources\GoodsReceipts\Pages\CreateGoodsReceipt;
use App\Filament\Resources\GoodsReceipts\Pages\EditGoodsReceipt;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\GoodsReceiptService;
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
    protected function poDisetujui(Product $produk, int $qty = 3, float $harga = 10_000_000): PurchaseOrder
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
}
