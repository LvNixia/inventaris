<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetAttachment;
use App\Models\AssetService;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\AttachmentType;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\DisposalReason;
use App\Models\Division;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\HandoverDocument;
use App\Models\PaymentTerm;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseBatch;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\ServiceKind;
use App\Models\ServiceResult;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BranchTransferService;
use App\Services\GoodsReceiptService;
use App\Services\HandoverService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseOrderService;
use App\Services\ServiceService;
use App\Services\TransactionService;
use App\Services\VendorPaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Data simulasi untuk mencoba laporan dan alur kerja aplikasi.
 *
 * Semua pergerakan aset dibuat lewat service aplikasi (serah terima, servis,
 * pindah cabang, pelepasan), bukan insert langsung, supaya riwayat transaksinya
 * konsisten dengan aturan bisnis yang berlaku.
 *
 * Jalankan dengan:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Kata sandi seluruh akun simulasi. Aman karena seeder menolak berjalan
     * di lingkungan production.
     */
    protected const KATA_SANDI_DEMO = 'password';

    protected User $admin;

    /** @var array<string, Branch> */
    protected array $branches = [];

    /** @var array<string, Employee> */
    protected array $employees = [];

    /** @var array<string, Product> */
    protected array $products = [];

    /** Akun yang mengajukan pesanan; persetujuannya di tangan admin pusat. */
    protected User $pengadaan;

    /** @var array<int, GoodsReceipt> */
    protected array $receipts = [];

    /**
     * Unit hasil pembelian, dikelompokkan per kunci barang.
     *
     * @var array<string, Collection<int, Asset>>
     */
    protected array $units = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Dibatalkan: data simulasi tidak boleh dijalankan di lingkungan production.');

            return;
        }

        if (Asset::withoutGlobalScopes()->exists()) {
            $this->command->warn('Dibatalkan: tabel aset sudah berisi data.');
            $this->command->line('Kosongkan dulu bila memang ingin memakai data simulasi:');
            $this->command->line('  php artisan migrate:fresh');
            $this->command->line('  php artisan db:seed --class=MasterSeeder');
            $this->command->line('  php artisan db:seed --class=DemoDataSeeder');

            return;
        }

        // Seeder harus bisa jalan di basis data yang baru dibuat, jadi akun
        // admin pusatnya dibuat di sini bila belum ada.
        $this->admin = User::firstOrCreate(
            ['email' => 'admin@indosurta.test'],
            [
                'name' => 'Admin Pusat',
                'password' => Hash::make(self::KATA_SANDI_DEMO),
                'role' => 'admin_pusat',
                'is_active' => true,
            ]
        );

        // Sebagian service memakai auth()->id() dan cakupan cabang milik
        // pengguna, jadi seeder ikut "masuk" sebagai admin pusat.
        Auth::login($this->admin);

        DB::transaction(function (): void {
            $this->siapkanMaster();
            $this->buatKaryawan();
            $this->buatPenggunaCabang();
            $this->buatBarang();
            $this->buatPembelian();
            $this->buatTagihan();
            $this->pengadaanBerjalan();
            $this->serahkanAset();
            $this->tarikSebagian();
            $this->buatServis();
            $this->pindahCabang();
            $this->laporkanKerusakan();
            $this->lepasAset();
            $this->buatLampiran();
        });

        $this->ringkasan();
    }

    protected function siapkanMaster(): void
    {
        foreach (Branch::all() as $branch) {
            $this->branches[$branch->code] = $branch;
        }

        foreach (['IT', 'Keuangan', 'HRD', 'Operasional', 'Marketing'] as $nama) {
            Division::firstOrCreate(['name' => $nama], ['is_active' => true]);
        }

        foreach (['Manager', 'Supervisor', 'Staff', 'Admin', 'Teknisi'] as $nama) {
            Position::firstOrCreate(['name' => $nama], ['is_active' => true]);
        }

        foreach (['Lenovo', 'Dell', 'HP', 'Asus', 'Epson', 'Samsung', 'Xiaomi', 'Logitech', 'Microsoft'] as $nama) {
            Brand::firstOrCreate(['name' => $nama], ['is_active' => true]);
        }

        // Syarat pembayaran melekat pada vendor: itulah yang tersalin ke pesanan
        // saat vendornya dipilih, lalu ikut ke fakturnya.
        $vendors = [
            ['Sinar Terang Komputer', 'toko', 'Bpk. Andi', '021-5551234', 'net30'],
            ['Mitra Data Solusi', 'keduanya', 'Ibu Sari', '021-5555678', 'net14'],
            ['Batam Jaya Elektronik', 'toko', 'Bpk. Rudi', '0778-451234', 'net45'],
            ['Servis Cepat Teknik', 'servis', 'Bpk. Hendra', '021-5559876', 'cod'],
        ];

        foreach ($vendors as [$nama, $tipe, $kontak, $telepon, $syarat]) {
            Vendor::firstOrCreate(['name' => $nama], [
                'type' => $tipe,
                'contact' => $kontak,
                'phone' => $telepon,
                'payment_term_id' => PaymentTerm::where('code', $syarat)->value('id'),
                'is_active' => true,
            ]);
        }
    }

    protected function buatKaryawan(): void
    {
        $data = [
            // kunci, nama, NIK, jabatan, divisi, cabang, aktif, boleh jadi saksi
            ['budi', 'Budi Santoso', 'IDS-0001', 'Manager', 'IT', 'JKT', true, true],
            ['siti', 'Siti Rahmawati', 'IDS-0002', 'Staff', 'Keuangan', 'JKT', true, false],
            ['agus', 'Agus Pratama', 'IDS-0003', 'Teknisi', 'IT', 'JKT', true, false],
            ['dewi', 'Dewi Lestari', 'IDS-0004', 'Supervisor', 'HRD', 'JKT', true, true],
            ['rizky', 'Rizky Ramadhan', 'IDS-0005', 'Staff', 'Marketing', 'JKT', true, false],
            ['putri', 'Putri Handayani', 'IDS-0006', 'Admin', 'Operasional', 'JKT', true, false],
            ['hendra', 'Hendra Wijaya', 'IDS-0007', 'Staff', 'IT', 'JKT', false, false],
            ['maya', 'Maya Anggraini', 'IDS-0008', 'Staff', 'Keuangan', 'JKT', false, false],
            ['fajar', 'Fajar Nugroho', 'IDS-0101', 'Supervisor', 'Operasional', 'BTM', true, true],
            ['lina', 'Lina Marlina', 'IDS-0102', 'Staff', 'Marketing', 'BTM', true, false],
            ['doni', 'Doni Kurniawan', 'IDS-0103', 'Teknisi', 'IT', 'BTM', true, false],
            ['sari', 'Sari Puspita', 'IDS-0104', 'Admin', 'HRD', 'BTM', true, false],
        ];

        foreach ($data as [$kunci, $nama, $nik, $jabatan, $divisi, $cabang, $aktif, $saksi]) {
            $this->employees[$kunci] = Employee::create([
                'name' => $nama,
                'nik' => $nik,
                'position_id' => Position::where('name', $jabatan)->value('id'),
                'division_id' => Division::where('name', $divisi)->value('id'),
                'branch_id' => $this->branches[$cabang]->id,
                'is_active' => $aktif,
                'can_sign_as_witness' => $saksi,
            ]);
        }

        // Admin pusat ditautkan ke seorang karyawan agar bisa menjadi
        // pihak pertama pada surat serah terima.
        $this->admin->update(['employee_id' => $this->employees['budi']->id]);
    }

    /**
     * Akun admin cabang Batam, supaya pembatasan data per cabang bisa dicoba.
     */
    protected function buatPenggunaCabang(): void
    {
        // Pemisahan tugas: pengaju pesanan bukan penyetujunya.
        $this->pengadaan = User::firstOrCreate(
            ['email' => 'pengadaan@indosurta.test'],
            [
                'name' => 'Staf Pengadaan',
                'password' => Hash::make(self::KATA_SANDI_DEMO),
                'role' => 'admin_pusat',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'batam@indosurta.test'],
            [
                'name' => 'Admin Cabang Batam',
                'password' => Hash::make(self::KATA_SANDI_DEMO),
                'role' => 'admin_cabang',
                'branch_id' => $this->branches['BTM']->id,
                'employee_id' => $this->employees['fajar']->id,
                'is_active' => true,
            ]
        );
    }

    /**
     * Katalog barang. Satu baris per jenis, dipakai berulang oleh pembelian.
     */
    protected function buatBarang(): void
    {
        $data = [
            // kunci, kategori, merek, model, spesifikasi
            ['thinkpad_t14', 'LAP', 'Lenovo', 'ThinkPad T14 Gen 3', ['CPU' => 'Core i7-1255U', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['latitude_5430', 'LAP', 'Dell', 'Latitude 5430', ['CPU' => 'Core i5-1235U', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['probook_440', 'LAP', 'HP', 'ProBook 440 G9', ['CPU' => 'Core i5-1235U', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 512GB']],
            ['expertbook_b1400', 'LAP', 'Asus', 'ExpertBook B1400', ['CPU' => 'Core i5-1135G7', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 256GB']],
            ['thinkpad_e14', 'LAP', 'Lenovo', 'ThinkPad E14 Gen 4', ['CPU' => 'Ryzen 5 5625U', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 512GB']],
            ['vostro_3520', 'LAP', 'Dell', 'Vostro 3520', ['CPU' => 'Core i5-1235U', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 512GB']],
            ['prodesk_400', 'PC', 'HP', 'ProDesk 400 G9', ['CPU' => 'Core i5-12500', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['thinkcentre_m70q', 'PC', 'Lenovo', 'ThinkCentre M70q', ['CPU' => 'Core i5-12400T', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['expertcenter_d500', 'PC', 'Asus', 'ExpertCenter D500SC', ['CPU' => 'Core i3-10105', 'RAM' => '8GB', 'Penyimpanan' => 'HDD 1TB']],
            ['dell_p2422h', 'MON', 'Dell', 'P2422H 24"', ['Ukuran' => '24 inci', 'Resolusi' => '1920x1080']],
            ['samsung_ls24', 'MON', 'Samsung', 'LS24C310 24"', ['Ukuran' => '24 inci', 'Resolusi' => '1920x1080']],
            ['epson_l3210', 'PRN', 'Epson', 'L3210 EcoTank', ['Jenis' => 'Inkjet', 'Fungsi' => 'Print/Scan/Copy']],
            ['hp_m211d', 'PRN', 'HP', 'LaserJet M211d', ['Jenis' => 'Laser', 'Fungsi' => 'Print']],
            ['galaxy_a54', 'SMP', 'Samsung', 'Galaxy A54 5G', ['RAM' => '8GB', 'Penyimpanan' => '256GB']],
            ['redmi_note12', 'SMP', 'Xiaomi', 'Redmi Note 12', ['RAM' => '6GB', 'Penyimpanan' => '128GB']],
            ['galaxy_tab_a8', 'TAB', 'Samsung', 'Galaxy Tab A8', ['Ukuran' => '10.5 inci', 'Penyimpanan' => '64GB']],
            ['mk270', 'ACC', 'Logitech', 'MK270 Keyboard Mouse', ['Koneksi' => 'Wireless']],
            ['c920', 'ACC', 'Logitech', 'C920 HD Webcam', ['Resolusi' => '1080p']],
            ['m365', 'LSS', 'Microsoft', 'Microsoft 365 Business Standard', ['Jenis' => 'Langganan tahunan']],
            ['win11_pro', 'LSS', 'Microsoft', 'Windows 11 Pro OEM', ['Jenis' => 'Lisensi perpetual']],
        ];

        foreach ($data as [$kunci, $kategori, $merek, $model, $spesifikasi]) {
            $this->products[$kunci] = Product::create([
                'category_id' => Category::where('code_prefix', $kategori)->value('id'),
                'brand_id' => Brand::where('name', $merek)->value('id'),
                'model' => $model,
                'specifications' => $spesifikasi,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Pembelian beserta unit-unitnya. Barang bernomor seri mendapat serial per
     * unit; barang lot seperti kabel dan lisensi dibuat tanpa serial.
     */
    protected function buatPembelian(): void
    {
        $vendorJkt = Vendor::where('name', 'Sinar Terang Komputer')->value('id');
        $vendorBtm = Vendor::where('name', 'Batam Jaya Elektronik')->value('id');

        // kunci barang, jumlah unit, harga, umur bulan, garansi bulan, cabang
        $data = [
            ['thinkpad_t14', 1, 18500000, 14, 36, 'JKT'],
            ['latitude_5430', 1, 16200000, 26, 36, 'JKT'],
            ['probook_440', 1, 14800000, 8, 24, 'JKT'],
            ['expertbook_b1400', 1, 12900000, 40, 24, 'JKT'],
            ['thinkpad_e14', 1, 13500000, 20, 24, 'BTM'],
            ['vostro_3520', 1, 11200000, 5, 24, 'BTM'],
            ['prodesk_400', 1, 12400000, 30, 36, 'JKT'],
            ['thinkcentre_m70q', 1, 13900000, 18, 36, 'JKT'],
            ['expertcenter_d500', 1, 9800000, 44, 24, 'BTM'],
            ['dell_p2422h', 4, 2650000, 18, 36, 'JKT'],
            ['samsung_ls24', 3, 1750000, 6, 24, 'BTM'],
            ['epson_l3210', 2, 2450000, 22, 24, 'JKT'],
            ['hp_m211d', 1, 3200000, 10, 12, 'BTM'],
            ['galaxy_a54', 1, 5900000, 12, 12, 'JKT'],
            ['redmi_note12', 1, 2700000, 16, 12, 'BTM'],
            ['galaxy_tab_a8', 1, 3300000, 24, 12, 'JKT'],
            ['mk270', 8, 350000, 14, 12, 'JKT'],
            ['c920', 3, 1250000, 15, 24, 'JKT'],
            ['m365', 10, 2100000, 4, 12, 'JKT'],
            ['win11_pro', 5, 2900000, 28, 0, 'JKT'],
            // Pembelian ulang barang yang sama: menambah batch, bukan barang baru.
            ['mk270', 4, 385000, 2, 12, 'JKT'],
            // Dua kiriman berdekatan dari vendor yang sama, supaya ada faktur
            // yang mencakup lebih dari satu penerimaan sekaligus.
            ['c920', 2, 1290000, 2, 24, 'JKT'],
            ['samsung_ls24', 2, 1795000, 4, 24, 'BTM'],
            ['dell_p2422h', 2, 2690000, 4, 36, 'BTM'],
        ];

        $urut = 0;

        foreach ($data as [$kunciBarang, $jumlah, $harga, $umurBulan, $garansiBulan, $cabang]) {
            $urut++;
            $produk = $this->products[$kunciBarang];
            $tanggalBeli = now()->subMonths($umurBulan)->startOfMonth()->addDays(($urut * 3) % 27);
            $perluSeri = $produk->requiresSerialNumber();
            $kodeKategori = $produk->category->code_prefix;

            $units = [];

            for ($i = 1; $i <= $jumlah; $i++) {
                $units[] = [
                    // Lisensi dilacak lewat kunci produknya, memakai kolom yang
                    // sama dengan nomor seri perangkat.
                    'serial_number' => match (true) {
                        $kodeKategori === 'LSS' => sprintf(
                            '%s-%s-%s-%s-%s',
                            strtoupper(substr(md5($urut.'a'.$i), 0, 5)),
                            strtoupper(substr(md5($urut.'b'.$i), 0, 5)),
                            strtoupper(substr(md5($urut.'c'.$i), 0, 5)),
                            strtoupper(substr(md5($urut.'d'.$i), 0, 5)),
                            strtoupper(substr(md5($urut.'e'.$i), 0, 5)),
                        ),
                        $perluSeri => sprintf('%s-%03d-%04d', strtoupper($kodeKategori), $urut, $i * 137 + $urut),
                        default => null,
                    },
                    'imei_1' => $kodeKategori === 'SMP'
                        ? '35'.str_pad((string) $urut, 6, '0', STR_PAD_LEFT).str_pad((string) ($i * 4321), 7, '0', STR_PAD_LEFT)
                        : null,
                ];
            }

            $unitBaru = $this->lewatiPengadaan(
                produk: $produk,
                units: $units,
                harga: $harga,
                garansiBulan: $garansiBulan,
                cabang: $cabang,
                vendorId: $cabang === 'BTM' ? $vendorBtm : $vendorJkt,
                tanggal: $tanggalBeli,
            );

            $this->units[$kunciBarang] = isset($this->units[$kunciBarang])
                ? $this->units[$kunciBarang]->concat($unitBaru)
                : $unitBaru;
        }
    }

    /**
     * Satu pembelian menempuh alur penuh: pesanan diajukan, disetujui, lalu
     * barangnya diterima. Unit aset lahir dari penerimaan itu.
     *
     * Data simulasi sengaja memakai jalur yang sama dengan pengguna, bukan
     * jalan pintas, supaya dokumen pesanan dan penerimaannya ikut terisi.
     *
     * @param  array<int, array<string, mixed>>  $units
     * @return Collection<int, Asset>
     */
    protected function lewatiPengadaan(
        Product $produk,
        array $units,
        float $harga,
        int $garansiBulan,
        string $cabang,
        ?int $vendorId,
        Carbon $tanggal,
    ): Collection {
        $po = $this->buatPesanan($produk, count($units), $harga, $garansiBulan, $cabang, $vendorId, $tanggal);

        $po = $this->setujuiPesanan($po);

        $gr = $this->buatPenerimaan($po, $units, $tanggal);

        $gr = app(GoodsReceiptService::class)->receive($gr);

        $this->receipts[] = $gr;

        return Asset::withoutGlobalScopes()
            ->whereIn('id', $gr->items()->pluck('asset_id')->filter())
            ->orderBy('id')
            ->get();
    }

    /**
     * Pesanan berstatus draf, lengkap dengan satu baris barang.
     *
     * Syarat pembayarannya disalin dari vendor, persis seperti yang dilakukan
     * formulir saat vendornya dipilih.
     */
    protected function buatPesanan(
        Product $produk,
        int $jumlah,
        float $harga,
        int $garansiBulan,
        string $cabang,
        ?int $vendorId,
        Carbon $tanggal,
    ): PurchaseOrder {
        // Pesanan selalu disusun staf pengadaan; persetujuannya di tangan
        // orang lain.
        Auth::login($this->pengadaan);

        $po = PurchaseOrder::create([
            'branch_id' => $this->branches[$cabang]->id,
            'vendor_id' => $vendorId,
            'payment_term_id' => Vendor::find($vendorId)?->payment_term_id
                ?? PaymentTerm::where('code', 'net30')->value('id'),
            'po_date' => $tanggal->copy()->subDays(7),
            'expected_date' => $tanggal,
            'status' => 'draft',
            'created_by' => $this->pengadaan->id,
        ]);

        $po->items()->create([
            'product_id' => $produk->id,
            'quantity' => $jumlah,
            'unit_price' => $harga,
            // Lisensi perangkat lunak dibeli tanpa PPN dari vendor ini.
            'tax_percent' => $produk->category->code_prefix === 'LSS' ? 0 : 11,
            'warranty_months' => $garansiBulan > 0 ? $garansiBulan : null,
        ]);

        return $po->refresh();
    }

    /**
     * Ajukan lalu setujui pesanan. Pengaju tidak boleh menyetujui pengajuannya
     * sendiri, jadi penggunanya berganti di tengah.
     */
    protected function setujuiPesanan(PurchaseOrder $po): PurchaseOrder
    {
        $poService = app(PurchaseOrderService::class);

        Auth::login($this->pengadaan);
        $po = $poService->submit($po);

        Auth::login($this->admin);

        return $poService->approve($po);
    }

    /**
     * Penerimaan berstatus draf atas sebuah pesanan, satu baris per unit.
     *
     * @param  array<int, array<string, mixed>>  $units
     */
    protected function buatPenerimaan(PurchaseOrder $po, array $units, Carbon $tanggal): GoodsReceipt
    {
        Auth::login($this->admin);

        $poItem = $po->items->first();

        $gr = GoodsReceipt::create([
            'purchase_order_id' => $po->id,
            'branch_id' => $po->branch_id,
            'vendor_id' => $po->vendor_id,
            'receipt_date' => $tanggal,
            'delivery_document_number' => 'SJ/'.$tanggal->format('Y/m').'/'.str_pad((string) $po->id, 3, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        foreach ($units as $unit) {
            $gr->items()->create([
                'purchase_order_item_id' => $poItem->id,
                'product_id' => $poItem->product_id,
                'serial_number' => $unit['serial_number'] ?? null,
                'imei_1' => $unit['imei_1'] ?? null,
                'unit_price' => $poItem->unit_price,
                'warranty_months' => $poItem->warranty_months,
            ]);
        }

        return $gr->refresh();
    }

    /**
     * Tagihan vendor beserta pembayarannya, dibuat dengan keadaan bermacam-macam
     * supaya laporan Hutang Vendor punya isi yang layak dilihat.
     */
    protected function buatTagihan(): void
    {
        Auth::login($this->admin);

        $invoiceService = app(PurchaseInvoiceService::class);
        $paymentService = app(VendorPaymentService::class);

        // Hanya penerimaan tujuh bulan terakhir yang ditagihkan. Pembelian
        // yang lebih tua dianggap sudah selesai urusan administrasinya, jadi
        // tidak ada faktur menganggur bertahun-tahun di daftar hutang.
        $perVendor = collect($this->receipts)
            ->filter(fn (GoodsReceipt $gr): bool => $gr->vendor_id !== null
                && $gr->receipt_date->greaterThanOrEqualTo(now()->subMonths(7)))
            ->sortByDesc(fn (GoodsReceipt $gr) => $gr->receipt_date)
            ->groupBy('vendor_id');

        $urut = 0;
        $faktur = [];

        // Tiap vendor ditagih dua kali. Satu faktur boleh mencakup dua
        // pengiriman, tetapi hanya yang berdekatan waktunya: tidak ada vendor
        // yang menagih kiriman bulan ini bersama kiriman setengah tahun lalu.
        foreach ($perVendor as $vendorId => $daftar) {
            foreach ($this->kelompokkanPenerimaan($daftar)->take(2) as $terpilih) {
                $urut++;
                $awal = $terpilih->first();
                $tanggalFaktur = $awal->receipt_date->copy()->addDays(3);

                // Nilai faktur dipecah persis seperti yang dihitung aplikasi:
                // subtotal dari harga unit, PPN dari persen pajak baris
                // pesanannya. Faktur tanpa PPN membuat laporan hutang menyebut
                // angka yang lebih kecil daripada nilai pesanannya sendiri.
                $subtotal = (float) $terpilih->sum(fn (GoodsReceipt $gr): float => $gr->total_value);
                $ppn = round($terpilih->sum(fn (GoodsReceipt $gr): float => $gr->tax_value));

                $faktur[] = $invoiceService->create([
                    'invoice_number' => 'INV/'.$tanggalFaktur->format('Y/m').'/'.str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
                    'vendor_id' => $vendorId,
                    'branch_id' => $awal->branch_id,
                    // Syarat pembayaran mengikuti pesanannya, bukan syarat
                    // vendor yang berlaku hari ini.
                    'payment_term_id' => $awal->purchaseOrder?->payment_term_id
                        ?? Vendor::find($vendorId)?->payment_term_id,
                    'invoice_date' => $tanggalFaktur,
                    'subtotal' => $subtotal,
                    'tax' => $ppn,
                    'total_amount' => $subtotal + $ppn,
                ], $terpilih->pluck('id')->all());
            }
        }

        if ($faktur === []) {
            return;
        }

        // Sebagian besar faktur dilunasi tepat waktu; yang menunggak hanya dua
        // terakhir. Perusahaan yang tidak pernah membayar apa pun bukan
        // keadaan yang masuk akal untuk dicontohkan.
        $faktur = collect($faktur);
        $menunggak = $faktur->slice(-2)->values();

        foreach ($faktur->slice(0, max(0, $faktur->count() - 2)) as $urutBayar => $lunas) {
            $paymentService->pay([
                'vendor_id' => $lunas->vendor_id,
                'branch_id' => $lunas->branch_id,
                'payment_date' => $lunas->invoice_date->copy()->addDays(10),
                'amount' => (float) $lunas->total_amount,
                'payment_method' => 'transfer',
                'reference_number' => 'TRF/BCA/'.(882140 + $urutBayar),
                'notes' => 'Pelunasan sesuai faktur.',
            ], [['purchase_invoice_id' => $lunas->id, 'amount' => (float) $lunas->total_amount]]);
        }

        // Yang kedua dari belakang baru dibayar sebagian; sisanya jadi hutang
        // berjalan.
        if ($menunggak->count() === 2) {
            $sebagian = $menunggak->first();
            $separuh = round((float) $sebagian->total_amount * 0.4);

            $paymentService->pay([
                'vendor_id' => $sebagian->vendor_id,
                'branch_id' => $sebagian->branch_id,
                'payment_date' => $sebagian->invoice_date->copy()->addDays(14),
                'amount' => $separuh,
                'payment_method' => 'transfer',
                'reference_number' => 'TRF/BCA/882199',
                'notes' => 'Pembayaran tahap pertama; sisanya menyusul.',
            ], [['purchase_invoice_id' => $sebagian->id, 'amount' => $separuh]]);
        }

        // Sisanya dibiarkan tanpa pembayaran. Yang tanggal fakturnya sudah
        // lewat syarat pembayarannya akan muncul sendiri sebagai lewat jatuh
        // tempo — tidak perlu tanggalnya dipaksa.
    }

    /**
     * Kelompokkan penerimaan menjadi calon faktur.
     *
     * Satu faktur mencakup paling banyak dua pengiriman, dan hanya bila
     * jaraknya tidak lebih dari 45 hari. Aturan itu yang membuat isinya masuk
     * akal dibaca: satu tagihan berisi kiriman yang memang berdekatan.
     *
     * @param  Collection<int, GoodsReceipt>  $daftar  Terurut dari yang terbaru.
     * @return Collection<int, Collection<int, GoodsReceipt>>
     */
    protected function kelompokkanPenerimaan(Collection $daftar): Collection
    {
        $kelompok = collect();

        foreach ($daftar as $gr) {
            $terakhir = $kelompok->last();

            $muat = $terakhir
                && $terakhir->count() < 2
                // Selisihnya diambil mutlak: daftarnya terurut dari yang
                // terbaru, jadi selisih bertandanya selalu negatif.
                && abs($terakhir->last()->receipt_date->diffInDays($gr->receipt_date)) <= 45;

            if ($muat) {
                $terakhir->push($gr);

                continue;
            }

            $kelompok->push(collect([$gr]));
        }

        return $kelompok;
    }

    /**
     * Pengadaan yang belum tuntas, satu untuk tiap keadaan yang bisa ditemui.
     *
     * Tanpa ini seluruh pesanan pada data simulasi berstatus Selesai, sehingga
     * tombol persetujuan, "Buat Penerimaan", dan penyaring status tidak punya
     * satu pun baris untuk dicoba.
     */
    protected function pengadaanBerjalan(): void
    {
        $vendorJkt = Vendor::where('name', 'Sinar Terang Komputer')->value('id');
        $vendorBtm = Vendor::where('name', 'Batam Jaya Elektronik')->value('id');
        $poService = app(PurchaseOrderService::class);

        // 1. Masih draf: baru disusun, belum diajukan.
        $this->buatPesanan(
            $this->products['mk270'], 6, 385_000, 12, 'JKT', $vendorJkt, now()->subDays(2),
        );

        // 2. Menunggu persetujuan admin pusat.
        $diajukan = $this->buatPesanan(
            $this->products['galaxy_a54'], 2, 5_950_000, 12, 'JKT', $vendorJkt, now()->addDays(5),
        );
        Auth::login($this->pengadaan);
        $poService->submit($diajukan);

        // 3. Sudah disetujui, barangnya belum datang. Inilah baris yang dipakai
        //    mencoba tombol "Buat Penerimaan".
        $this->setujuiPesanan($this->buatPesanan(
            $this->products['thinkpad_e14'], 3, 13_900_000, 24, 'BTM', $vendorBtm, now()->addDays(9),
        ));

        // 4. Diterima sebagian: dipesan 4 monitor, baru 2 yang dikirim.
        $sebagian = $this->setujuiPesanan($this->buatPesanan(
            $this->products['dell_p2422h'], 4, 2_690_000, 36, 'JKT', $vendorJkt, now()->subDays(10),
        ));

        $gr = $this->buatPenerimaan($sebagian, [
            ['serial_number' => 'MON-901-0001'],
            ['serial_number' => 'MON-901-0002'],
        ], now()->subDays(8));

        $this->receipts[] = app(GoodsReceiptService::class)->receive($gr);

        // 5. Dibatalkan sebelum barangnya dikirim.
        $batal = $this->setujuiPesanan($this->buatPesanan(
            $this->products['epson_l3210'], 2, 2_495_000, 24, 'JKT', $vendorJkt, now()->subDays(20),
        ));
        $poService->cancel($batal, 'Vendor menyatakan barangnya kosong sampai kuartal depan.');

        // 6. Penerimaan yang masih draf karena nomor serinya belum lengkap.
        //    Menyetujuinya akan ditolak sampai seluruh baris bernomor seri —
        //    itulah gerbang yang ingin diperlihatkan.
        $menunggu = $this->setujuiPesanan($this->buatPesanan(
            $this->products['prodesk_400'], 2, 12_650_000, 36, 'JKT', $vendorJkt, now()->subDays(4),
        ));

        $this->buatPenerimaan($menunggu, [
            ['serial_number' => 'PC-902-0001'],
            ['serial_number' => null],
        ], now()->subDays(3));

        Auth::login($this->admin);
    }

    /**
     * Ambil satu unit tertentu dari sebuah barang.
     */
    protected function unit(string $kunciBarang, int $nomor = 1): Asset
    {
        return $this->units[$kunciBarang][$nomor - 1]->refresh();
    }

    /**
     * Serah terima resmi: dokumen dibuat sebagai draf lalu diterbitkan,
     * sehingga nomor surat, transaksi, dan pemegang aset terbentuk otomatis.
     */
    protected function serahkanAset(): void
    {
        $handover = app(HandoverService::class);

        $paket = [
            [
                'cabang' => 'JKT',
                'penerima' => 'siti',
                'saksi' => 'dewi',
                'bulanLalu' => 10,
                'barang' => [['latitude_5430', 1], ['dell_p2422h', 1], ['mk270', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'agus',
                'saksi' => 'dewi',
                'bulanLalu' => 7,
                'barang' => [['probook_440', 1], ['c920', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'rizky',
                'saksi' => 'dewi',
                'bulanLalu' => 5,
                'barang' => [['galaxy_a54', 1], ['galaxy_tab_a8', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'hendra', // karyawan nonaktif: sengaja masih memegang aset
                'saksi' => 'dewi',
                'bulanLalu' => 15,
                'barang' => [['prodesk_400', 1], ['dell_p2422h', 2]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'maya', // karyawan nonaktif kedua
                'saksi' => 'dewi',
                'bulanLalu' => 12,
                'barang' => [['thinkpad_t14', 1]],
            ],
            [
                'cabang' => 'BTM',
                'penerima' => 'lina',
                'saksi' => 'fajar',
                'bulanLalu' => 6,
                'barang' => [['thinkpad_e14', 1], ['samsung_ls24', 1]],
            ],
            [
                'cabang' => 'BTM',
                'penerima' => 'doni',
                'saksi' => 'fajar',
                'bulanLalu' => 3,
                'barang' => [['redmi_note12', 1], ['hp_m211d', 1]],
            ],
        ];

        foreach ($paket as $item) {
            $penyerah = $item['cabang'] === 'BTM' ? $this->employees['fajar'] : $this->employees['budi'];

            $doc = HandoverDocument::create([
                'document_date' => $this->tanggalSerahTerima($item['bulanLalu'], $item['barang']),
                'branch_id' => $this->branches[$item['cabang']]->id,
                'first_party_id' => $penyerah->id,
                'second_party_id' => $this->employees[$item['penerima']]->id,
                'witness_id' => $this->employees[$item['saksi']]->id,
                'status' => 'draft',
                'created_by' => $this->admin->id,
            ]);

            foreach ($item['barang'] as [$kunciBarang, $nomorUnit]) {
                $doc->items()->create([
                    'asset_id' => $this->unit($kunciBarang, $nomorUnit)->id,
                    'user_employee_id' => $this->employees[$item['penerima']]->id,
                ]);
            }

            $handover->issue($doc);
        }

        // Satu surat sengaja dibiarkan berstatus draf, untuk laporan
        // "surat yang belum diterbitkan".
        $draf = HandoverDocument::create([
            'document_date' => now()->subDays(4),
            'branch_id' => $this->branches['JKT']->id,
            'first_party_id' => $this->employees['budi']->id,
            'second_party_id' => $this->employees['putri']->id,
            'witness_id' => $this->employees['dewi']->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $draf->items()->create([
            'asset_id' => $this->unit('mk270', 2)->id,
            'user_employee_id' => $this->employees['putri']->id,
        ]);
    }

    /**
     * Tanggal surat serah terima: sekian bulan lalu, tetapi tidak pernah
     * mendahului pembelian barang yang diserahkan.
     */
    protected function tanggalSerahTerima(int $bulanLalu, array $barang): Carbon
    {
        $tanggal = now()->subMonths($bulanLalu)->startOfDay();

        $pembelianTerbaru = collect($barang)
            ->map(fn (array $baris) => $this->unit($baris[0], $baris[1])->purchaseBatch?->purchase_date)
            ->filter()
            ->max();

        if ($pembelianTerbaru && $tanggal->lessThan($pembelianTerbaru)) {
            // Beri jeda seminggu agar barang sempat diterima gudang dulu.
            return $pembelianTerbaru->copy()->addDays(7);
        }

        return $tanggal;
    }

    /**
     * Sebagian barang ditarik kembali ke gudang.
     */
    protected function tarikSebagian(): void
    {
        $transaksi = app(TransactionService::class);
        $baik = Condition::where('code', 'baik')->value('id');

        // Tanggal ditulis eksplisit karena kedua unit ini nanti dikirim ke
        // cabang lain; penarikan harus mendahului pengirimannya.
        $transaksi->return($this->unit('c920', 1), [
            'from_employee_id' => $this->employees['agus']->id,
            'transaction_date' => now()->subDays(25)->toDateString(),
            'condition_after_id' => $baik,
            'notes' => 'Dikembalikan karena ganti perangkat.',
        ]);

        $transaksi->return($this->unit('galaxy_tab_a8', 1), [
            'from_employee_id' => $this->employees['rizky']->id,
            'transaction_date' => now()->subDays(10)->toDateString(),
            'condition_after_id' => $baik,
            'notes' => 'Kegiatan pameran sudah selesai.',
        ]);
    }

    protected function buatServis(): void
    {
        $service = app(ServiceService::class);
        $vendorServis = Vendor::where('name', 'Servis Cepat Teknik')->value('id');
        $perbaikan = ServiceKind::where('code', 'perbaikan')->value('id');
        $upgrade = ServiceKind::where('code', 'upgrade')->value('id');
        $perawatan = ServiceKind::where('code', 'perawatan')->value('id');

        // 1. Servis selesai: keyboard laptop diganti.
        $rec = $service->open($this->unit('expertbook_b1400', 1), [
            'service_kind_id' => $perbaikan,
            'performed_by' => 'vendor',
            'vendor_id' => $vendorServis,
            'item_left' => true,
            'started_at' => now()->subDays(38),
            'expected_at' => now()->subDays(31),
            'ticket_ref' => 'TKT-2026-0181',
            'complaint' => 'Beberapa tombol keyboard tidak berfungsi.',
        ]);

        $service->close($rec->refresh(), [
            'finished_at' => now()->subDays(30),
            'service_result_id' => ServiceResult::where('code', 'ganti_part')->value('id'),
            'work_done' => 'Penggantian modul keyboard dan pembersihan bagian dalam.',
            'cost_service' => 250000,
            'cost_parts' => 850000,
            // Garansi tiga bulan terhitung sejak pekerjaan selesai.
            'service_warranty_until' => now()->subDays(30)->addMonths(3),
            'condition_after_id' => Condition::where('code', 'baik')->value('id'),
            'status_setelah' => 'spare',
        ]);

        // 2. Upgrade selesai: penambahan RAM. Spesifikasi unit ini jadi berbeda
        //    dari spesifikasi bawaan barangnya.
        $rec = $service->open($this->unit('expertcenter_d500', 1), [
            'service_kind_id' => $upgrade,
            'performed_by' => 'internal',
            'item_left' => true,
            'started_at' => now()->subDays(20),
            'expected_at' => now()->subDays(18),
            'complaint' => 'Permintaan penambahan RAM agar tidak lambat.',
            'spec_after' => ['CPU' => 'Core i3-10105', 'RAM' => '16GB', 'Penyimpanan' => 'HDD 1TB'],
        ]);

        $service->close($rec->refresh(), [
            'finished_at' => now()->subDays(17),
            'service_result_id' => ServiceResult::where('code', 'diperbaiki')->value('id'),
            'work_done' => 'RAM ditambah menjadi 16GB.',
            'cost_service' => 0,
            'cost_parts' => 620000,
            'condition_after_id' => Condition::where('code', 'baik')->value('id'),
            'status_setelah' => 'spare',
        ]);

        // 3. Servis masih berjalan dan sudah lewat estimasi. Hanya satu unit
        //    printer yang masuk servis; unit lainnya tetap bisa diserahkan.
        $service->open($this->unit('epson_l3210', 1), [
            'service_kind_id' => $perbaikan,
            'performed_by' => 'vendor',
            'vendor_id' => $vendorServis,
            'item_left' => true,
            'started_at' => now()->subDays(12),
            'expected_at' => now()->subDays(4),
            'ticket_ref' => 'TKT-2026-0207',
            'complaint' => 'Hasil cetak bergaris, head diduga tersumbat.',
        ]);

        // 4. Perawatan rutin yang masih berjalan sesuai jadwal.
        $service->open($this->unit('thinkcentre_m70q', 1), [
            'service_kind_id' => $perawatan,
            'performed_by' => 'internal',
            'item_left' => false,
            'started_at' => now()->subDays(2),
            'expected_at' => now()->addDays(3),
            'complaint' => 'Pembersihan berkala dan pembaruan sistem operasi.',
        ]);
    }

    protected function pindahCabang(): void
    {
        $transfer = app(BranchTransferService::class);

        // Unit yang sama berpindah cabang; tidak ada lagi pemecahan baris.
        $webcam = $this->unit('c920', 1);

        $transfer->send($webcam, [
            'to_branch_id' => $this->branches['BTM']->id,
            'transaction_date' => now()->subDays(21)->toDateString(),
            'notes' => 'Resi JNE 884120033. Penambahan webcam untuk cabang Batam.',
        ]);

        $transfer->receive($webcam->refresh(), [
            'transaction_date' => now()->subDays(17)->toDateString(),
            'condition_id' => Condition::where('code', 'baik')->value('id'),
            'notes' => 'Barang diterima dalam keadaan baik.',
        ]);

        // Masih dalam perjalanan: muncul di laporan "kiriman belum diterima".
        $transfer->send($this->unit('galaxy_tab_a8', 1), [
            'to_branch_id' => $this->branches['BTM']->id,
            'transaction_date' => now()->subDays(6)->toDateString(),
            'notes' => 'Resi JNE 884120987. Menunggu konfirmasi penerimaan.',
        ]);
    }

    /**
     * Kerusakan yang dilaporkan setelah aset dipakai, bukan bawaan pembelian.
     */
    protected function laporkanKerusakan(): void
    {
        app(TransactionService::class)->changeStatus($this->unit('vostro_3520', 1), [
            'status_id' => AssetStatus::where('code', 'broken')->value('id'),
            'condition_after_id' => Condition::where('code', 'rusak_ringan')->value('id'),
            'transaction_date' => now()->subDays(35)->toDateString(),
            'notes' => 'Engsel layar retak dan baterai cepat habis; menunggu penjadwalan servis.',
        ]);
    }

    protected function lepasAset(): void
    {
        $transaksi = app(TransactionService::class);

        // Lisensi OEM terikat pada perangkat aslinya dan tidak bisa dipindah
        // atau dijual, jadi ikut dihapuskan saat PC-nya dipensiunkan.
        foreach ([1, 2] as $nomor) {
            $transaksi->dispose($this->unit('win11_pro', $nomor), [
                'transaction_date' => now()->subDays(45)->toDateString(),
                'disposal_reason_id' => DisposalReason::where('code', 'dibuang')->value('id'),
                'notes' => 'PC yang memakai lisensi ini sudah dipensiunkan; lisensi OEM tidak dapat dipindahkan.',
            ]);
        }

        // Hilang saat masih dipegang karyawan: sistem otomatis menarik unitnya
        // dulu, lalu menghapusbukukan. Muncul pada laporan kehilangan.
        $transaksi->dispose($this->unit('redmi_note12', 1), [
            'transaction_date' => now()->subDays(20)->toDateString(),
            'disposal_reason_id' => DisposalReason::where('code', 'hilang')->value('id'),
            'notes' => 'Hilang saat perjalanan dinas; sudah dilaporkan ke atasan.',
        ]);
    }

    /**
     * Berkas pendukung aset. Isinya berkas contoh berukuran kecil, cukup untuk
     * menguji unduhan dan pratinjau tanpa membebani penyimpanan.
     */
    protected function buatLampiran(): void
    {
        $disk = Storage::disk(config('filesystems.default'));
        $jenis = AttachmentType::pluck('id', 'code');

        $laptopServis = $this->unit('expertbook_b1400', 1);

        $servisSelesai = AssetService::where('asset_id', $laptopServis->id)
            ->whereNotNull('finished_at')
            ->first();

        $daftar = [
            [$this->unit('thinkpad_t14', 1), 'photo', 'foto-thinkpad-t14.png', null],
            [$this->unit('thinkpad_t14', 1), 'invoice', 'faktur-thinkpad-t14.pdf', null],
            [$this->unit('latitude_5430', 1), 'warranty', 'kartu-garansi-latitude-5430.pdf', null],
            [$laptopServis, 'service_receipt', 'tanda-terima-servis-keyboard.pdf', $servisSelesai?->id],
        ];

        foreach ($daftar as [$asset, $kodeJenis, $namaBerkas, $servisId]) {
            $isi = str_ends_with($namaBerkas, '.png') ? $this->berkasPng() : $this->berkasPdf($namaBerkas);
            $path = 'asset-attachments/demo-'.$namaBerkas;

            $disk->put($path, $isi);

            AssetAttachment::create([
                'asset_id' => $asset->id,
                'service_id' => $servisId,
                'attachment_type_id' => $jenis[$kodeJenis],
                'path' => $path,
                'original_name' => $namaBerkas,
                'mime' => str_ends_with($namaBerkas, '.png') ? 'image/png' : 'application/pdf',
                'size' => strlen($isi),
                'uploaded_by' => $this->admin->id,
            ]);
        }
    }

    /**
     * PNG 1x1 piksel; cukup untuk menguji pratinjau gambar.
     */
    protected function berkasPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    /**
     * PDF satu halaman berisi judul berkas; cukup untuk menguji unduhan.
     */
    protected function berkasPdf(string $judul): string
    {
        $teks = '('.str_replace([')', '('], '', $judul).') Tj';
        $isiHalaman = "BT /F1 14 Tf 60 760 Td {$teks} ET";

        $objek = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length ".strlen($isiHalaman)." >>\nstream\n{$isiHalaman}\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offset = [];

        foreach ($objek as $i => $o) {
            $offset[$i + 1] = strlen($pdf);
            $pdf .= $o;
        }

        $awalXref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objek) + 1)."\n0000000000 65535 f \n";

        foreach ($offset as $posisi) {
            $pdf .= str_pad((string) $posisi, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objek) + 1)." /Root 1 0 R >>\nstartxref\n{$awalXref}\n%%EOF";
    }

    protected function ringkasan(): void
    {
        $baris = [
            'Karyawan' => Employee::withoutGlobalScopes()->count(),
            'Barang (katalog)' => Product::count(),
            'Pembelian (batch)' => PurchaseBatch::withoutGlobalScopes()->count(),
            'Unit aset' => Asset::withoutGlobalScopes()->count(),
            'Pesanan pembelian' => PurchaseOrder::withoutGlobalScopes()->count(),
            '  di antaranya berjalan' => PurchaseOrder::withoutGlobalScopes()
                ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'Penerimaan barang' => GoodsReceipt::withoutGlobalScopes()->count(),
            '  di antaranya draf' => GoodsReceipt::withoutGlobalScopes()
                ->where('status', 'draft')->count(),
            'Faktur vendor' => PurchaseInvoice::withoutGlobalScopes()->count(),
            'Surat serah terima' => HandoverDocument::withoutGlobalScopes()->count(),
            'Transaksi' => AssetTransaction::count(),
            'Catatan servis' => AssetService::count(),
            'Lampiran' => AssetAttachment::count(),
        ];

        $this->command->newLine();
        $this->command->info('Data simulasi berhasil dibuat:');

        foreach ($baris as $label => $jumlah) {
            $this->command->line(sprintf('  %-22s %d', $label, $jumlah));
        }

        $this->command->newLine();
        $this->command->line('Skenario yang ikut tersedia untuk laporan:');
        $this->command->line('  - 2 karyawan nonaktif yang masih memegang aset');
        $this->command->line('  - 1 servis lewat estimasi dan 1 servis masih berjalan');
        $this->command->line('  - 1 pengiriman antar cabang yang belum diterima');
        $this->command->line('  - 1 aset rusak yang menunggu penjadwalan servis');
        $this->command->line('  - pelepasan aset: dibuang dan hilang');
        $this->command->line('  - 1 surat serah terima berstatus draf');
        $this->command->line('  - 4 lampiran berkas, salah satunya tertaut ke catatan servis');
        $this->command->line('  - 1 barang dibeli dua kali dengan harga berbeda');
        $this->command->line('  - faktur vendor: lunas, dibayar sebagian, dan lewat jatuh tempo');
        $this->command->line('  - pesanan berjalan: draf, menunggu persetujuan, disetujui, diterima sebagian, dibatalkan');
        $this->command->line('  - 1 penerimaan draf yang tertahan karena nomor serinya belum lengkap');

        $this->command->newLine();
        $this->command->line('Akun untuk masuk (kata sandi: '.self::KATA_SANDI_DEMO.'):');
        $this->command->line('  admin@indosurta.test   Admin Pusat');
        $this->command->line('  batam@indosurta.test   Admin Cabang Batam');
        $this->command->line('  pengadaan@indosurta.test  Staf Pengadaan (pengaju PO)');
    }
}
