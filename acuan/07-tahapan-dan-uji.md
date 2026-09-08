# 07 — Tahapan & Skenario Uji

## 1. Tahapan

| Tahap | Isi | Kriteria selesai |
|---|---|---|
| 0 | Bukti konsep lingkungan tanpa Node: `composer create-project laravel/laravel` (PHP 8.5), `filament/filament` + `filament:install --panels`, `barryvdh/laravel-dompdf` | Panel tampil & login, satu PDF percobaan terunduh. Paket yang belum mendukung Laravel 13 → putuskan Opsi B atau tunggu, di sini |
| 1 | Skema DB, enum, seeder master, auth & peran, BranchScope, Policy, MasterRegistry + halaman master (alur 15) | Login per peran; semua master bisa tambah/ubah/nonaktif/hapus/gabung; uji 10, 18, 31–34 |
| 2 | Register aset, kode aset, import aset & transaksi, data awal | Uji 17 |
| 3 | Ledger, StockCalculator, TransactionService, validasi | Uji 1–8, 19–20 |
| 4 | Surat: draft, terbit, PDF, penomoran | Uji 9, 11, 13 |
| 5 | Dashboard, riwayat, pemeriksaan data | Angka dashboard = data awal §5 |
| 6 | Export, backup workbook, import master/pembaruan | Export → edit → import ulang tanpa error |
| 7 | Pembatalan, pindah cabang, mutasi, karyawan nonaktif, lampiran, bukti pengembalian, bantuan | Uji 12, 14–16, 21–23, 26 |
| 7b | Servis & upgrade (alur 14): catatan, halaman Servis Berjalan, tanda terima servis, perubahan spesifikasi | Uji 27–30 |
| 8 | Produksi: HTTPS, backup DB, queue worker, scheduler, uji restore | Restore ke server kosong berhasil |

## 2. Skenario uji

| # | Skenario | Alur |
|---|---|---|
| 1 | Laptop baru → terbitkan surat ke A → `qty_available` 0, pemegang A, status Dipakai, hilang dari dropdown surat | 02 |
| 2 | Tarik kembali dari A → sisa 1, pemegang kosong, muncul lagi di dropdown | 03 |
| 3 | Pindahkan A → B: transaksi `in` + draft surat B; setelah terbit, transaksi `out`, pemegang B, urutan benar | 04 |
| 4 | Dipakai dua kali tanpa Kembali → ditolak | 02 |
| 5 | Kembali untuk barang yang belum keluar → ditolak | 03 |
| 6 | Mouse qty 20: 5 ke A, 3 ke B → sisa 12, saldo A=5, B=3; A mengembalikan 6 → ditolak | 02, 03 |
| 7 | Aset Dipakai lalu Rusak → sisa tetap, status Rusak, tidak muncul di dropdown surat | 05 |
| 8 | Serial wajib kosong → ditolak; serial duplikat → ditolak | 01 |
| 9 | Dua admin cabang menerbitkan bersamaan di cabang sama → nomor tidak duplikat | 02 |
| 10 | Admin cabang Batam tidak melihat/mengubah aset Jakarta | — |
| 11 | Batalkan surat → transaksi pembalik, sisa kembali, PDF ber-watermark | 07 |
| 12 | Batalkan surat yang asetnya sudah dikembalikan → ditolak dengan pesan yang menyebut transaksi penghalang | 07 |
| 13 | Dua draft memuat aset sama → yang pertama terbit, yang kedua gagal per baris dan tetap bisa diedit | 02 |
| 14 | Pindah cabang aset yang masih dipegang → ditolak; setelah ditarik → Dalam Perjalanan → konfirmasi tujuan → `branch_id` berubah, cabang asal tidak melihatnya | 08 |
| 15 | Nonaktifkan karyawan yang memegang 2 aset → ditolak; "Tarik semua" → 2 transaksi `in` → berhasil | 10 |
| 16 | Pengembalian kondisi "Rusak Ringan" → `assets.condition_id` berubah, riwayat menyimpan kondisi | 03 |
| 17 | Data awal → nilai sama dengan [06-data-awal.md](06-data-awal.md) §5 | 12 |
| 18 | Viewer tertaut karyawan divisi Finance hanya melihat aset divisi Finance | — |
| 19 | Dilepas 5 dari 20 mouse di gudang → `qty_available` 15, unit aktif 15, dilepas 5; melepas 3 yang dipegang B → ditolak | 06 |
| 20 | Laptop dipegang A lalu Servis → pemegang tetap A, status Servis | 05 |
| 21 | Mutasi karyawan yang memegang 3 dari 10 mouse → aset asal qty 7, saldo karyawan 0, available tetap; aset baru qty 3 di cabang tujuan, saldo karyawan 3, available 0 | 09 |
| 22 | Batalkan surat mouse 2 unit ke A, padahal setelah itu 3 unit lain diserahkan ke B → boleh (saldo A masih 2); batalkan setelah A mengembalikan 1 → ditolak | 07 |
| 23 | Admin pusat menerbitkan surat aset Jakarta ke karyawan Batam → `branch_id` aset menjadi Batam, ada `branch_transfer` otomatis; admin cabang Batam melihatnya | 02 |
| 24 | `stock:recompute` dijalankan dari CLI menyentuh semua cabang (BranchScope tidak aktif tanpa user) | — |
| 25 | Hapus aset lalu buat aset baru dengan nomor seri yang sama → berhasil | 01 |
| 26 | Barang hilang saat dipegang A (alasan Hilang) → `return` dari A + `disposal`; saldo A 0; unit aktif berkurang | 06 |
| 27 | Buka servis (vendor, barang ditinggal) pada laptop yang dipegang A → status Servis, pemegang tetap A, catatan `open`, tidak muncul di dropdown surat; buka servis kedua → ditolak | 14 |
| 28 | Selesaikan upgrade RAM 8→16 GB, kembali ke pemegang → `assets.specifications` berubah, `spec_before` tersimpan, status Dipakai, pemegang A, biaya tercatat, riwayat punya `service_id` | 14 |
| 29 | Selesaikan servis hasil `cannot_fix` → status Rusak; lanjut Dilepas → `disposal` | 14 |
| 30 | Upgrade tanpa barang ditinggal → status tetap Dipakai, tetap ada transaksi ber-`service_id` di riwayat | 14 |
| 31 | Tambah merk baru dari "+ Tambah baru" di form aset → tersimpan dan terpilih; tambah merk dengan nama sama (beda kapital/spasi) → ditolak | 15 |
| 32 | Hapus merk yang dipakai 3 aset → ditolak, tombol menjadi Nonaktifkan; setelah nonaktif, merk hilang dari dropdown tapi tetap tampil di 3 aset | 15 |
| 33 | Gabungkan vendor "Tokopedia " ke "Tokopedia" → semua aset & servis pindah, sumber terhapus, audit tercatat | 15 |
| 34 | Ubah nama status `spare` menjadi "Gudang IT" → kode tetap bekerja (pengembalian masih memakai status itu); hapus status sistem → ditolak; tambah status baru "Dipinjam" (out, transferable, requires_qty) → bisa dipakai di surat | 15 |

Feature test Pest/PHPUnit di MySQL/MariaDB test DB (bukan SQLite: uji 9 & 13 butuh `lockForUpdate()`, uji 8 butuh unique index parsial). Logika stok diuji sebagai unit test langsung pada `StockCalculator`, `HandoverService`, `TransactionService`. Fixture data awal disalin ke `tests/Fixtures/`.
