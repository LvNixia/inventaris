<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas Persetujuan Mandiri
    |--------------------------------------------------------------------------
    |
    | Pesanan dengan nilai sampai angka ini boleh disetujui oleh pengajunya
    | sendiri; di atasnya wajib disetujui Admin Pusat yang berbeda orang.
    |
    | Alasannya praktis: kalau tiap belanja kecil harus menunggu atasan membuka
    | aplikasi, yang terjadi bukan kontrol melainkan akun atasan yang dipinjam.
    | Batas ini menaruh pemeriksaannya di tempat yang memang bernilai.
    |
    | Isi 0 bila seluruh pesanan, berapa pun nilainya, harus disetujui orang
    | lain.
    |
    */

    'batas_persetujuan_mandiri' => (float) env('PENGADAAN_BATAS_PERSETUJUAN', 5_000_000),

];
