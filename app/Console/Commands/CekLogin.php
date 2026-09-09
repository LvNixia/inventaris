<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Diagnosa kegagalan login, terutama untuk perbedaan antara lingkungan lokal
 * dan server produksi. Perintah ini hanya membaca keadaan, tidak mengubah data.
 */
class CekLogin extends Command
{
    protected $signature = 'app:cek-login {--uji-masuk : Uji email dan kata sandi langsung di server}';

    protected $description = 'Memeriksa penyebab umum gagal login: sesi, kunci aplikasi, izin folder, dan data pengguna';

    protected int $masalah = 0;

    public function handle(): int
    {
        $this->info('Pemeriksaan kesiapan login');

        $this->bagian('Lingkungan');
        $this->periksa('APP_KEY terisi', filled(config('app.key')),
            'Jalankan: php artisan key:generate. Tanpa ini sesi tidak bisa dienkripsi.');
        $this->baris('APP_ENV', (string) config('app.env'));
        $this->baris('APP_URL', (string) config('app.url'));
        $this->baris('APP_DEBUG', config('app.debug') ? 'true' : 'false');

        $appUrl = (string) config('app.url');

        $this->periksa('APP_URL tanpa garis miring di akhir', $appUrl === rtrim($appUrl, '/'),
            'APP_URL diakhiri "/" sehingga alamat yang dibentuk aplikasi menjadi ganda '
            . '(contoh: //livewire/update). Tombol bisa tidak memberi reaksi apa pun. '
            . 'Hapus garis miring terakhir di .env, lalu jalankan php artisan config:clear.');

        $this->periksa('APP_DEBUG mati di produksi',
            ! (config('app.env') === 'production' && config('app.debug')),
            'APP_DEBUG=true di server produksi membocorkan jejak error dan isi konfigurasi ke pengunjung. '
            . 'Setel APP_DEBUG=false setelah selesai memeriksa masalah ini.');

        $configDicache = file_exists(base_path('bootstrap/cache/config.php'));
        $this->baris('Config di-cache', $configDicache ? 'ya' : 'tidak');

        if ($configDicache) {
            $this->catatan('Bila .env baru diubah, jalankan: php artisan config:clear');
        }

        $this->bagian('Cookie dan sesi');

        $urlHttps = Str::startsWith((string) config('app.url'), 'https://');
        $cookieAman = (bool) config('session.secure');

        $this->baris('Driver sesi', (string) config('session.driver'));
        $this->baris('SESSION_SECURE_COOKIE', $cookieAman ? 'true' : 'false');
        $this->baris('SESSION_DOMAIN', config('session.domain') ?? 'null');
        $this->baris('APP_URL memakai https', $urlHttps ? 'ya' : 'tidak');

        $this->periksa('Cookie aman cocok dengan skema URL', ! ($cookieAman && ! $urlHttps),
            'SESSION_SECURE_COOKIE bernilai true tetapi situs diakses lewat http. Cookie sesi tidak akan '
            . 'tersimpan di browser, sehingga login selalu kembali ke halaman login. Setel false, atau layani situs lewat https.');

        if (filled(config('session.domain'))) {
            $hostUrl = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
            $domain = ltrim((string) config('session.domain'), '.');

            $this->periksa('SESSION_DOMAIN cocok dengan APP_URL', $hostUrl !== '' && Str::endsWith($hostUrl, $domain),
                "SESSION_DOMAIN ({$domain}) tidak cocok dengan host APP_URL ({$hostUrl}); cookie akan ditolak browser.");
        }

        $this->bagian('Penyimpanan sesi');

        $driver = config('session.driver');

        if ($driver === 'database') {
            $tabel = (string) config('session.table', 'sessions');
            $ada = Schema::hasTable($tabel);

            $this->periksa("Tabel {$tabel} ada", $ada,
                'Jalankan: php artisan migrate. Tanpa tabel ini sesi tidak tersimpan dan login selalu gagal.');

            if ($ada) {
                try {
                    $kunci = 'cek-login-' . Str::random(8);

                    DB::table($tabel)->insert([
                        'id' => $kunci,
                        'payload' => 'uji',
                        'last_activity' => time(),
                    ]);

                    DB::table($tabel)->where('id', $kunci)->delete();

                    $this->periksa('Bisa menulis ke tabel sesi', true, '');
                } catch (\Throwable $e) {
                    $this->periksa('Bisa menulis ke tabel sesi', false, 'Gagal menulis: ' . $e->getMessage());
                }
            }
        } elseif ($driver === 'file') {
            $dir = (string) config('session.files');

            $this->periksa("Folder sesi bisa ditulis ({$dir})", is_dir($dir) && is_writable($dir),
                'Perbaiki izin: chmod -R 775 storage lalu samakan pemiliknya dengan pengguna web server.');
        }


        $this->bagian('Uji simpan-baca sesi');

        try {
            $store = app('session')->driver();
            $penanda = 'cek-' . Str::random(10);

            $store->put('cek_login_penanda', $penanda);
            $store->save();

            $idSesi = $store->getId();

            // Buka ulang sesi dengan id yang sama, meniru request berikutnya.
            $store2 = app('session')->driver();
            $store2->setId($idSesi);
            $store2->start();

            $kembali = $store2->get('cek_login_penanda');

            $this->periksa('Sesi bertahan antar request', $kembali === $penanda,
                'Nilai yang disimpan tidak terbaca kembali. Inilah sebabnya login selalu balik ke halaman login: '
                . 'aplikasi menganggap pengguna belum masuk. Periksa driver sesi, tabel sessions, dan izin folder di atas.');

            $store2->forget('cek_login_penanda');
            $store2->save();
        } catch (\Throwable $e) {
            $this->periksa('Sesi bertahan antar request', false, 'Gagal menguji sesi: ' . $e->getMessage());
        }

        $this->baris('SESSION_SAME_SITE', (string) (config('session.same_site') ?? 'null'));
        $this->baris('SESSION_LIFETIME', (string) config('session.lifetime') . ' menit');

        // Config yang di-cache bisa memuat nilai lama sehingga berbeda dengan .env saat ini.
        $envPath = base_path('.env');

        if ($configDicache && is_readable($envPath)) {
            $isiEnv = (string) file_get_contents($envPath);

            if (preg_match('/^APP_KEY=(.*)$/m', $isiEnv, $cocok)) {
                $kunciEnv = trim($cocok[1], " \"'");

                $this->periksa('APP_KEY di config cache sama dengan .env',
                    $kunciEnv === '' || $kunciEnv === (string) config('app.key'),
                    'Config yang di-cache memakai APP_KEY lama, sehingga cookie sesi tidak bisa dibaca. '
                    . 'Jalankan: php artisan config:clear lalu php artisan config:cache');
            }
        }

        $this->bagian('Endpoint Livewire');

        // Livewire membentuk alamat endpoint-nya dari APP_KEY. Bila rute di-cache
        // memakai kunci lama sementara halaman dibentuk dengan kunci baru, berkas
        // JS tidak ditemukan dan tombol tidak memberi reaksi apa pun.
        $kelasResolver = \Livewire\Mechanisms\HandleRequests\EndpointResolver::class;

        if (! class_exists($kelasResolver)) {
            $this->baris('Resolver endpoint', 'tidak tersedia pada versi Livewire ini');
        } else {
            $prefix = $kelasResolver::prefix();
            $pathScript = $kelasResolver::scriptPath(minified: true);
            $pathUpdate = $kelasResolver::updatePath();

            $this->baris('Prefix (dari APP_KEY)', $prefix);
            $this->baris('Berkas JS', $pathScript);
            $this->baris('Endpoint update', $pathUpdate);

            $rute = collect(app('router')->getRoutes()->getRoutes())
                ->map(fn ($r) => '/' . ltrim($r->uri(), '/'))
                ->filter(fn ($uri) => Str::startsWith($uri, '/livewire'))
                ->values()
                ->all();

            $cocokScript = in_array($pathScript, $rute, true) || in_array($kelasResolver::scriptPath(minified: false), $rute, true);
            $cocokUpdate = in_array($pathUpdate, $rute, true);

            $this->periksa('Rute berkas JS Livewire terdaftar', $cocokScript,
                'Alamat JS yang dipakai halaman tidak punya rute. Browser akan menerima 404, '
                . 'Livewire tidak aktif, dan tombol masuk tidak memberi reaksi apa pun.');

            $this->periksa('Rute endpoint update terdaftar', $cocokUpdate,
                'Endpoint update Livewire tidak terdaftar pada alamat yang dipakai halaman.');

            if ($rute !== []) {
                $this->baris('Rute livewire terdaftar', implode(', ', array_slice($rute, 0, 3)) . (count($rute) > 3 ? ' ...' : ''));
            }

            // Rute yang di-cache bisa menyimpan prefix dari APP_KEY lama.
            $berkasRuteCache = glob(base_path('bootstrap/cache/routes-*.php')) ?: [];

            $this->baris('Rute di-cache', $berkasRuteCache ? 'ya' : 'tidak');

            foreach ($berkasRuteCache as $berkas) {
                $isi = (string) @file_get_contents($berkas);

                $this->periksa('Prefix pada cache rute sesuai APP_KEY sekarang',
                    str_contains($isi, ltrim($prefix, '/')),
                    'Cache rute masih memakai prefix Livewire dari APP_KEY lama, sedangkan halaman '
                    . 'dibentuk memakai prefix baru. Jalankan: php artisan route:clear (atau optimize:clear).');
            }
        }

        $this->bagian('Izin folder');

        foreach ([
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache',
        ] as $relatif) {
            $path = base_path($relatif);

            $this->periksa("{$relatif} bisa ditulis", is_dir($path) && is_writable($path),
                "Perbaiki: chmod -R 775 {$relatif} dan samakan pemiliknya dengan pengguna web server.");
        }

        $this->bagian('Data pengguna');

        try {
            $pengguna = \App\Models\User::withoutGlobalScopes()->get();

            $this->baris('Jumlah pengguna', (string) $pengguna->count());
            $this->periksa('Ada pengguna terdaftar', $pengguna->isNotEmpty(),
                'Belum ada baris pada tabel users.');

            $peranSah = array_map(fn ($kasus) => $kasus->value, \App\Enums\Role::cases());

            foreach ($pengguna as $u) {
                $sandi = (string) ($u->getAttributes()['password'] ?? '');
                $peran = $u->getAttributes()['role'] ?? null;

                $catatan = [];

                if (! Str::startsWith($sandi, ['$2y$', '$argon2'])) {
                    $catatan[] = 'kata sandi bukan hash, login pasti gagal';
                }

                if (! in_array($peran, $peranSah, true)) {
                    $catatan[] = "peran '{$peran}' tidak dikenali (sah: " . implode(', ', $peranSah) . ')';
                }

                if (! (bool) $u->is_active) {
                    $catatan[] = 'ditandai nonaktif';
                }

                $this->line(sprintf('   %s %-32s %s',
                    $catatan ? 'x' : 'v',
                    $u->email,
                    $catatan ? implode('; ', $catatan) : 'siap dipakai login',
                ));

                if ($catatan) {
                    $this->masalah++;
                }
            }
        } catch (\Throwable $e) {
            $this->periksa('Tabel users terbaca', false,
                'Gagal membaca tabel users: ' . $e->getMessage() . '. Periksa koneksi database lalu jalankan migrate.');
        }

        $this->bagian('Catatan error terakhir');

        $logPath = storage_path('logs/laravel.log');

        if (! is_readable($logPath)) {
            $this->baris('Berkas log', 'tidak ada atau tidak terbaca');
        } else {
            $barisLog = @file($logPath) ?: [];
            $error = [];

            foreach (array_reverse($barisLog) as $satu) {
                if (preg_match('/^\[([^\]]+)\].*?(ERROR|CRITICAL|EMERGENCY): (.*)$/', $satu, $cocok)) {
                    $error[] = sprintf('%s  %s', $cocok[1], Str::limit(trim($cocok[3]), 140));

                    if (count($error) >= 5) {
                        break;
                    }
                }
            }

            if ($error === []) {
                $this->line('   v Tidak ada baris ERROR pada log');
            } else {
                $this->line('   Lima error terakhir, paling baru di atas:');

                foreach ($error as $satu) {
                    $this->line('      - ' . $satu);
                }

                $this->catatan('Coba login sekali lagi, lalu jalankan perintah ini kembali. '
                    . 'Bila muncul error baru dengan waktu yang cocok, itulah penyebabnya.');
            }
        }

        if ($this->option('uji-masuk')) {
            $this->bagian('Uji kredensial langsung di server');

            $email = (string) $this->ask('Email');
            $sandi = (string) $this->secret('Kata sandi, tidak ditampilkan');

            $adaEmail = \App\Models\User::withoutGlobalScopes()->where('email', $email)->exists();

            $this->periksa('Email terdaftar', $adaEmail,
                'Tidak ada pengguna dengan email tersebut. Periksa daftar email pada bagian Data pengguna di atas.');

            if ($adaEmail) {
                \Illuminate\Support\Facades\Auth::logout();

                $lolos = \Illuminate\Support\Facades\Auth::attempt([
                    'email' => $email,
                    'password' => $sandi,
                ]);

                $this->periksa('Kata sandi cocok', $lolos,
                    'Kata sandi tidak cocok dengan yang tersimpan di database.');

                if ($lolos) {
                    \Illuminate\Support\Facades\Auth::logout();
                    $this->line('      -> Kredensial sah di sisi server. Bila di browser tetap gagal, '
                        . 'masalahnya ada pada permintaan Livewire atau cookie, bukan pada kata sandi.');
                }
            }
        }

        $this->newLine();

        if ($this->masalah === 0) {
            $this->info('Tidak ditemukan masalah pada sisi aplikasi.');
            $this->line('Bila login masih gagal, lihat storage/logs/laravel.log saat mencoba masuk.');
        } else {
            $this->warn("Ditemukan {$this->masalah} hal yang perlu diperbaiki, ditandai x di atas.");
        }

        return $this->masalah === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function bagian(string $judul): void
    {
        $this->newLine();
        $this->line('<options=bold>' . $judul . '</>');
    }

    protected function baris(string $label, string $nilai): void
    {
        $this->line(sprintf('   . %-26s %s', $label, $nilai));
    }

    protected function periksa(string $label, bool $lulus, string $saran): void
    {
        $this->line(sprintf('   %s %s', $lulus ? 'v' : 'x', $label));

        if (! $lulus) {
            $this->masalah++;

            if ($saran !== '') {
                $this->line('      -> ' . $saran);
            }
        }
    }

    protected function catatan(string $teks): void
    {
        $this->line('      -> ' . $teks);
    }
}
