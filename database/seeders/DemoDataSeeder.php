<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Condition;
use App\Models\DisposalReason;
use App\Models\Division;
use App\Models\Employee;
use App\Models\HandoverDocument;
use App\Models\Position;
use App\Models\ServiceKind;
use App\Models\ServiceResult;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetService;
use App\Services\BranchTransferService;
use App\Services\HandoverService;
use App\Services\ServiceService;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Data simulasi untuk mencoba laporan dan alur kerja aplikasi.
 *
 * Semua pergerakan aset dibuat lewat service aplikasi (serah terima, servis,
 * pindah cabang, pelepasan), bukan insert langsung, supaya perhitungan stok
 * dan riwayat transaksinya konsisten dengan aturan bisnis yang berlaku.
 *
 * Jalankan dengan:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    protected User $admin;

    /** @var array<string, Branch> */
    protected array $branches = [];

    /** @var array<string, Employee> */
    protected array $employees = [];

    /** @var array<string, Asset> */
    protected array $assets = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Dibatalkan: data simulasi tidak boleh dijalankan di lingkungan production.');

            return;
        }

        if (Asset::withoutGlobalScopes()->exists()) {
            $this->command->warn('Dibatalkan: tabel aset sudah berisi data.');
            $this->command->line('Kosongkan dulu bila memang ingin memakai data simulasi:');
            $this->command->line('  php artisan migrate:fresh --seed');
            $this->command->line('  php artisan db:seed --class=DemoDataSeeder');

            return;
        }

        $this->admin = User::where('role', 'admin_pusat')->firstOrFail();

        // Sebagian service memakai auth()->id() dan cakupan cabang milik
        // pengguna, jadi seeder ikut "masuk" sebagai admin pusat.
        Auth::login($this->admin);

        DB::transaction(function (): void {
            $this->siapkanMaster();
            $this->buatKaryawan();
            $this->buatAset();
            $this->serahkanAset();
            $this->tarikSebagian();
            $this->buatServis();
            $this->pindahCabang();
            $this->lepasAset();
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

        $vendors = [
            ['Sinar Terang Komputer', 'toko', 'Bpk. Andi', '021-5551234'],
            ['Mitra Data Solusi', 'keduanya', 'Ibu Sari', '021-5555678'],
            ['Batam Jaya Elektronik', 'toko', 'Bpk. Rudi', '0778-451234'],
            ['Servis Cepat Teknik', 'servis', 'Bpk. Hendra', '021-5559876'],
        ];

        foreach ($vendors as [$nama, $tipe, $kontak, $telepon]) {
            Vendor::firstOrCreate(['name' => $nama], [
                'type' => $tipe,
                'contact' => $kontak,
                'phone' => $telepon,
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

    protected function buatAset(): void
    {
        $registered = AssetStatus::where('code', 'registered')->firstOrFail();
        $baik = Condition::where('code', 'baik')->firstOrFail();
        $rusakRingan = Condition::where('code', 'rusak_ringan')->firstOrFail();

        // kunci, kategori, merek, model, jumlah, harga, bulan lalu dibeli, garansi (bulan), cabang, spesifikasi
        $data = [
            ['lap1', 'LAP', 'Lenovo', 'ThinkPad T14 Gen 3', 1, 18500000, 14, 36, 'JKT', ['CPU' => 'Core i7-1255U', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['lap2', 'LAP', 'Dell', 'Latitude 5430', 1, 16200000, 26, 36, 'JKT', ['CPU' => 'Core i5-1235U', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['lap3', 'LAP', 'HP', 'ProBook 440 G9', 1, 14800000, 8, 24, 'JKT', ['CPU' => 'Core i5-1235U', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 512GB']],
            ['lap4', 'LAP', 'Asus', 'ExpertBook B1400', 1, 12900000, 40, 24, 'JKT', ['CPU' => 'Core i5-1135G7', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 256GB']],
            ['lap5', 'LAP', 'Lenovo', 'ThinkPad E14 Gen 4', 1, 13500000, 20, 24, 'BTM', ['CPU' => 'Ryzen 5 5625U', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 512GB']],
            ['lap6', 'LAP', 'Dell', 'Vostro 3520', 1, 11200000, 5, 24, 'BTM', ['CPU' => 'Core i5-1235U', 'RAM' => '8GB', 'Penyimpanan' => 'SSD 512GB']],
            ['pc1', 'PC', 'HP', 'ProDesk 400 G9', 1, 12400000, 30, 36, 'JKT', ['CPU' => 'Core i5-12500', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['pc2', 'PC', 'Lenovo', 'ThinkCentre M70q', 1, 13900000, 18, 36, 'JKT', ['CPU' => 'Core i5-12400T', 'RAM' => '16GB', 'Penyimpanan' => 'SSD 512GB']],
            ['pc3', 'PC', 'Asus', 'ExpertCenter D500SC', 1, 9800000, 44, 24, 'BTM', ['CPU' => 'Core i3-10105', 'RAM' => '8GB', 'Penyimpanan' => 'HDD 1TB']],
            ['mon1', 'MON', 'Dell', 'P2422H 24"', 4, 2650000, 18, 36, 'JKT', ['Ukuran' => '24 inci', 'Resolusi' => '1920x1080']],
            ['mon2', 'MON', 'Samsung', 'LS24C310 24"', 3, 1750000, 6, 24, 'BTM', ['Ukuran' => '24 inci', 'Resolusi' => '1920x1080']],
            ['prn1', 'PRN', 'Epson', 'L3210 EcoTank', 2, 2450000, 22, 24, 'JKT', ['Jenis' => 'Inkjet', 'Fungsi' => 'Print/Scan/Copy']],
            ['prn2', 'PRN', 'HP', 'LaserJet M211d', 1, 3200000, 10, 12, 'BTM', ['Jenis' => 'Laser', 'Fungsi' => 'Print']],
            ['smp1', 'SMP', 'Samsung', 'Galaxy A54 5G', 2, 5900000, 12, 12, 'JKT', ['RAM' => '8GB', 'Penyimpanan' => '256GB']],
            ['smp2', 'SMP', 'Xiaomi', 'Redmi Note 12', 2, 2700000, 16, 12, 'BTM', ['RAM' => '6GB', 'Penyimpanan' => '128GB']],
            ['tab1', 'TAB', 'Samsung', 'Galaxy Tab A8', 2, 3300000, 24, 12, 'JKT', ['Ukuran' => '10.5 inci', 'Penyimpanan' => '64GB']],
            ['acc1', 'ACC', 'Logitech', 'MK270 Keyboard Mouse', 8, 350000, 9, 12, 'JKT', ['Koneksi' => 'Wireless']],
            ['acc2', 'ACC', 'Logitech', 'C920 HD Webcam', 3, 1250000, 15, 24, 'JKT', ['Resolusi' => '1080p']],
            ['lss1', 'LSS', 'Microsoft', 'Microsoft 365 Business Standard', 10, 2100000, 4, 12, 'JKT', ['Jenis' => 'Langganan tahunan']],
            ['lss2', 'LSS', 'Microsoft', 'Windows 11 Pro OEM', 5, 2900000, 28, 0, 'JKT', ['Jenis' => 'Lisensi perpetual']],
        ];

        $vendorJkt = Vendor::where('name', 'Sinar Terang Komputer')->value('id');
        $vendorBtm = Vendor::where('name', 'Batam Jaya Elektronik')->value('id');
        $service = app(AssetService::class);
        $urut = 0;

        foreach ($data as [$kunci, $kategori, $merek, $model, $jumlah, $harga, $umurBulan, $garansiBulan, $cabang, $spesifikasi]) {
            $urut++;
            $tanggalBeli = now()->subMonths($umurBulan)->startOfMonth()->addDays(($urut * 3) % 27);
            $perluSeri = Category::where('code_prefix', $kategori)->value('requires_serial');

            $asset = $service->create([
                'category_id' => Category::where('code_prefix', $kategori)->value('id'),
                'brand_id' => Brand::where('name', $merek)->value('id'),
                'model' => $model,
                'quantity' => $jumlah,
                'serial_number' => ($perluSeri && $jumlah === 1)
                    ? strtoupper($kategori) . '-' . str_pad((string) $urut, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999)
                    : null,
                'condition_id' => ($kunci === 'lap4' ? $rusakRingan->id : $baik->id),
                'specifications' => $spesifikasi,
                'purchase_date' => $tanggalBeli,
                'vendor_id' => $cabang === 'BTM' ? $vendorBtm : $vendorJkt,
                'invoice_number' => 'INV/' . $tanggalBeli->format('Y/m') . '/' . str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
                'unit_price' => $harga,
                'warranty_until' => $garansiBulan > 0 ? $tanggalBeli->copy()->addMonths($garansiBulan) : null,
                'branch_id' => $this->branches[$cabang]->id,
            ]);

            // Aset baru berstatus kosong; ditandai "Terdaftar" agar bisa diserahkan.
            $asset->update(['current_status_id' => $registered->id]);

            $this->assets[$kunci] = $asset->refresh();
        }
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
                'barang' => [['lap2', 1], ['mon1', 1], ['acc1', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'agus',
                'saksi' => 'dewi',
                'bulanLalu' => 7,
                'barang' => [['lap3', 1], ['acc2', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'rizky',
                'saksi' => 'dewi',
                'bulanLalu' => 5,
                'barang' => [['smp1', 1], ['tab1', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'hendra', // karyawan nonaktif: sengaja masih memegang aset
                'saksi' => 'dewi',
                'bulanLalu' => 15,
                'barang' => [['pc1', 1], ['mon1', 1]],
            ],
            [
                'cabang' => 'JKT',
                'penerima' => 'maya', // karyawan nonaktif kedua
                'saksi' => 'dewi',
                'bulanLalu' => 12,
                'barang' => [['lap1', 1]],
            ],
            [
                'cabang' => 'BTM',
                'penerima' => 'lina',
                'saksi' => 'fajar',
                'bulanLalu' => 6,
                'barang' => [['lap5', 1], ['mon2', 1]],
            ],
            [
                'cabang' => 'BTM',
                'penerima' => 'doni',
                'saksi' => 'fajar',
                'bulanLalu' => 3,
                'barang' => [['smp2', 1], ['prn2', 1]],
            ],
        ];

        foreach ($paket as $item) {
            $penyerah = $item['cabang'] === 'BTM' ? $this->employees['fajar'] : $this->employees['budi'];

            $doc = HandoverDocument::create([
                'document_date' => now()->subMonths($item['bulanLalu']),
                'branch_id' => $this->branches[$item['cabang']]->id,
                'first_party_id' => $penyerah->id,
                'second_party_id' => $this->employees[$item['penerima']]->id,
                'witness_id' => $this->employees[$item['saksi']]->id,
                'status' => 'draft',
                'created_by' => $this->admin->id,
            ]);

            foreach ($item['barang'] as [$kunciAset, $jumlah]) {
                $doc->items()->create([
                    'asset_id' => $this->assets[$kunciAset]->id,
                    'quantity' => $jumlah,
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
            'asset_id' => $this->assets['acc1']->id,
            'quantity' => 1,
            'user_employee_id' => $this->employees['putri']->id,
        ]);
    }

    /**
     * Sebagian barang ditarik kembali ke gudang.
     */
    protected function tarikSebagian(): void
    {
        $transaksi = app(TransactionService::class);
        $baik = Condition::where('code', 'baik')->value('id');

        $transaksi->return($this->assets['acc2']->refresh(), [
            'from_employee_id' => $this->employees['agus']->id,
            'quantity' => 1,
            'condition_after_id' => $baik,
            'notes' => 'Dikembalikan karena ganti perangkat.',
        ]);

        $transaksi->return($this->assets['tab1']->refresh(), [
            'from_employee_id' => $this->employees['rizky']->id,
            'quantity' => 1,
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
        $rec = $service->open($this->assets['lap4']->refresh(), [
            'service_kind_id' => $perbaikan,
            'performed_by' => 'vendor',
            'vendor_id' => $vendorServis,
            'item_left' => true,
            'started_at' => now()->subDays(38),
            'expected_at' => now()->subDays(31),
            'ticket_ref' => 'TKT-2024-0181',
            'complaint' => 'Beberapa tombol keyboard tidak berfungsi.',
        ]);

        $service->close($rec->refresh(), [
            'finished_at' => now()->subDays(30),
            'service_result_id' => ServiceResult::where('code', 'ganti_part')->value('id'),
            'work_done' => 'Penggantian modul keyboard dan pembersihan bagian dalam.',
            'cost_service' => 250000,
            'cost_parts' => 850000,
            'service_warranty_until' => now()->addDays(60),
            'condition_after_id' => Condition::where('code', 'baik')->value('id'),
            'status_setelah' => 'spare',
        ]);

        // 2. Upgrade selesai: penambahan RAM.
        $rec = $service->open($this->assets['pc3']->refresh(), [
            'service_kind_id' => $upgrade,
            'performed_by' => 'internal',
            'item_left' => true,
            'started_at' => now()->subDays(20),
            'expected_at' => now()->subDays(18),
            'complaint' => 'Permintaan penambahan RAM agar tidak lambat.',
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

        // 3. Servis masih berjalan dan sudah lewat estimasi.
        $service->open($this->assets['prn1']->refresh(), [
            'service_kind_id' => $perbaikan,
            'performed_by' => 'vendor',
            'vendor_id' => $vendorServis,
            'item_left' => true,
            'started_at' => now()->subDays(12),
            'expected_at' => now()->subDays(4),
            'ticket_ref' => 'TKT-2024-0207',
            'complaint' => 'Hasil cetak bergaris, head diduga tersumbat.',
        ]);

        // 4. Perawatan rutin yang masih berjalan sesuai jadwal.
        $service->open($this->assets['pc2']->refresh(), [
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

        // Hanya barang yang seluruhnya ada di gudang yang boleh dikirim;
        // acc2 dan tab1 sudah ditarik kembali dari pemegangnya di tahap sebelumnya.
        $transfer->send($this->assets['acc2']->refresh(), [
            'to_branch_id' => $this->branches['BTM']->id,
            'quantity' => 1,
            'transaction_date' => now()->subDays(21),
            'notes' => 'Resi JNE 884120033. Penambahan webcam untuk cabang Batam.',
        ]);

        $dikirim = Asset::withoutGlobalScopes()
            ->where('branch_id', $this->branches['BTM']->id)
            ->whereHas('currentStatus', fn ($q) => $q->where('code', 'in_transit'))
            ->latest('id')
            ->first();

        if ($dikirim) {
            $transfer->receive($dikirim, [
                'transaction_date' => now()->subDays(17),
                'condition_id' => Condition::where('code', 'baik')->value('id'),
                'notes' => 'Barang diterima dalam keadaan baik.',
            ]);
        }

        // Masih dalam perjalanan: muncul di laporan "kiriman belum diterima".
        $transfer->send($this->assets['tab1']->refresh(), [
            'to_branch_id' => $this->branches['BTM']->id,
            'quantity' => 1,
            'transaction_date' => now()->subDays(6),
            'notes' => 'Resi JNE 884120987. Menunggu konfirmasi penerimaan.',
        ]);
    }

    protected function lepasAset(): void
    {
        $transaksi = app(TransactionService::class);

        // Dijual karena sudah tua.
        $transaksi->dispose($this->assets['lss2']->refresh(), [
            'quantity' => 2,
            'disposal_reason_id' => DisposalReason::where('code', 'dijual')->value('id'),
            'notes' => 'Lisensi tidak terpakai, dialihkan ke unit lain. Nilai jual Rp 3.000.000.',
        ]);

        // Hilang: memunculkan angka pada laporan kehilangan.
        $transaksi->dispose($this->assets['smp2']->refresh(), [
            'quantity' => 1,
            'disposal_reason_id' => DisposalReason::where('code', 'hilang')->value('id'),
            'notes' => 'Hilang saat perjalanan dinas; sudah dilaporkan ke atasan.',
        ]);
    }

    protected function ringkasan(): void
    {
        $baris = [
            'Karyawan' => Employee::withoutGlobalScopes()->count(),
            'Aset' => Asset::withoutGlobalScopes()->count(),
            'Unit aset' => (int) Asset::withoutGlobalScopes()->sum('quantity'),
            'Surat serah terima' => HandoverDocument::withoutGlobalScopes()->count(),
            'Transaksi' => \App\Models\AssetTransaction::count(),
            'Catatan servis' => \App\Models\AssetService::count(),
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
        $this->command->line('  - pelepasan aset: dijual dan hilang');
        $this->command->line('  - 1 surat serah terima berstatus draf');
    }
}
