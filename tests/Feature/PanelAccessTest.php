<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Uji asap untuk memastikan panel admin terpasang dan terlindungi.
 * Seluruh antarmuka aplikasi ada di dalam panel, jadi hal-hal inilah yang
 * paling awal rusak bila konfigurasi panel atau rute bermasalah.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_depan_dialihkan_ke_panel_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_tamu_tidak_bisa_masuk_panel_tanpa_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_halaman_login_dapat_dibuka(): void
    {
        $this->get('/admin/login')->assertSuccessful();
    }

    /**
     * Panduan memuat banyak markah tetap di dalam Blade, jadi kesalahan
     * penulisannya baru ketahuan saat halaman dibuka.
     */
    public function test_halaman_panduan_dapat_dibuka(): void
    {
        $this->actingAs($this->adminPusat())
            ->get('/admin/panduan')
            ->assertSuccessful()
            ->assertSee('Panduan Penggunaan');
    }

    /**
     * Setiap daftar dan laporan menyusun kueri sendiri terhadap tabel aset.
     * Membukanya satu per satu menangkap kolom atau relasi yang tertinggal.
     *
     * @return array<string, array{string}>
     */
    public static function halamanUtama(): array
    {
        return [
            'daftar barang' => ['/admin/products'],
            'daftar pembelian' => ['/admin/purchase-batches'],
            'daftar aset' => ['/admin/assets'],
            'daftar servis' => ['/admin/asset-services'],
            'daftar transaksi' => ['/admin/asset-transactions'],
            'daftar surat serah terima' => ['/admin/handover-documents'],
            'daftar karyawan' => ['/admin/employees'],
            'laporan posisi aset' => ['/admin/laporan-posisi-aset'],
            'laporan kepemilikan' => ['/admin/laporan-kepemilikan'],
            'laporan servis' => ['/admin/laporan-servis'],
            'laporan mutasi' => ['/admin/laporan-mutasi'],
            'pemeriksaan data' => ['/admin/data-check'],
            'impor aset' => ['/admin/impor-aset'],
        ];
    }

    #[DataProvider('halamanUtama')]
    public function test_halaman_utama_dapat_dibuka(string $url): void
    {
        $this->actingAs($this->adminPusat())
            ->get($url)
            ->assertSuccessful();
    }

    protected function adminPusat(): User
    {
        $admin = new User;
        $admin->id = 1;
        $admin->name = 'Admin Uji';
        $admin->email = 'uji@contoh.test';
        $admin->role = 'admin_pusat';
        $admin->is_active = true;
        $admin->exists = true;

        return $admin;
    }
}
