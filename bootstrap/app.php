<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplikasi berjalan di belakang Cloudflare Tunnel: cloudflared menutup
        // koneksi HTTPS lalu meneruskannya ke nginx sebagai HTTP dari mesin yang
        // sama. Tanpa ini Laravel menganggap request sebagai http dan menulis
        // alamat aset dengan http://, sehingga diblokir browser sebagai mixed
        // content pada halaman https dan Livewire tidak pernah termuat.
        //
        // Hanya alamat lokal dan jaringan privat yang dipercaya, jadi header
        // X-Forwarded-* dari internet tidak bisa dipalsukan seandainya origin
        // suatu saat terekspos langsung.
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
