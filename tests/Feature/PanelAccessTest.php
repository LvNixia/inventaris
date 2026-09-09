<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Uji asap untuk memastikan panel admin terpasang dan terlindungi.
 * Seluruh antarmuka aplikasi ada di dalam panel, jadi tiga hal inilah yang
 * paling awal rusak bila konfigurasi panel atau rute bermasalah.
 */
class PanelAccessTest extends TestCase
{
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
}
