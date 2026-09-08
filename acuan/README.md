# Acuan Aplikasi Web — Inventaris Aset IT & Surat Serah Terima INDOSURTA

Dokumen acuan dibagi per topik. Baca berurutan saat pertama kali; setelah itu tiap file bisa dirujuk sendiri-sendiri.

| File | Isi |
|---|---|
| [01-arsitektur.md](01-arsitektur.md) | Stack (Laravel 13, PHP 8.5, tanpa Node), struktur direktori, multi-cabang |
| [02-model-data.md](02-model-data.md) | Diagram ER, semua tabel & kolom, indeks, seed status/kategori |
| [03-aturan-bisnis.md](03-aturan-bisnis.md) | Kode aset, rumus stok, pemegang, validasi, nomor surat |
| [04-modul-dan-peran.md](04-modul-dan-peran.md) | Halaman/modul aplikasi, dashboard, peran & hak akses |
| [05-surat-pdf.md](05-surat-pdf.md) | Template surat serah terima & bukti pengembalian |
| [06-data-awal.md](06-data-awal.md) | Master awal, 24 aset, 24 riwayat, nilai yang diharapkan |
| [07-tahapan-dan-uji.md](07-tahapan-dan-uji.md) | Urutan pengerjaan dan 20 skenario uji |
| [08-keamanan-nonfungsional.md](08-keamanan-nonfungsional.md) | File privat, auth, audit, queue, backup, konfigurasi |
| [09-keputusan-terbuka.md](09-keputusan-terbuka.md) | Hal yang masih harus diputuskan |
| [10-desain-ui.md](10-desain-ui.md) | Responsif (mobile/tablet/desktop), palet pastel biru muda, tipografi, komponen, pemetaan ke Filament. Wireframe: kanvas "Wireframe Inventaris Aset" di claude.ai (artifact) |
| [11-master-data.md](11-master-data.md) | Semua daftar master (13) yang bisa ditambah/ubah/nonaktif/hapus/gabung, kolom sistem, aturan hapus |
| [alur/](alur/README.md) | **Satu file per aksi** (15 aksi, termasuk servis & upgrade dan kelola master): langkah UI, proses server, validasi, perubahan data |

## Keputusan yang sudah ditetapkan

| Keputusan | Nilai |
|---|---|
| Stack | Laravel 13 + PHP 8.5 + MySQL/MariaDB, **tanpa Node.js** |
| Pengguna | Multi-cabang (13 cabang); peran admin pusat / admin cabang / viewer |
| Output surat | PDF dihasilkan server, tersimpan, bisa diunduh ulang |
| Import/export | Import master, aset, transaksi lewat template; export mengikuti filter; backup workbook |

## Prinsip rancangan (wajib dipahami sebelum menulis kode)

1. **Ledger adalah sumber kebenaran.** Setiap perpindahan/perubahan status aset adalah satu baris `asset_transactions`. Transaksi tidak dihapus dan tidak diedit; salah catat diperbaiki dengan transaksi koreksi. Kolom ringkasan di aset (`qty_*`, `current_*`) hanya cache yang ditulis oleh satu service (`StockCalculator`).
2. **Kode aset dan nomor surat dibuat server** dari sequence yang dikunci, dan tidak pernah berubah.
3. **Surat dan transaksi satu paket.** Menerbitkan surat = membuat transaksi Keluar untuk tiap barang, dalam satu DB transaction. Tidak ada transaksi Keluar tanpa surat (kecuali data awal).
4. **Dua peran pada penerima**: penanggung jawab yang menandatangani (`to_employee_id`) dan pemakai sebenarnya (`user_employee_id`). Keduanya dari master karyawan, bukan teks bebas.
5. **Validasi di server** (Form Request + Service + constraint DB), bukan hanya di UI. Import melewati validasi yang sama dengan form.
6. **Logika bisnis tidak bergantung pada UI framework.** Filament/Livewire hanya lapisan tampilan; semua aturan ada di `app/Services`.

## Hasil tinjauan logika (v2)
Tinjauan menyeluruh menemukan dan memperbaiki: (1) pemisahan barang massal saat mutasi karyawan yang menghilangkan riwayat "keluar" — sekarang unit ikut beserta ledgernya ([alur/09](alur/09-mutasi-karyawan.md)); (2) pembatalan surat barang massal yang terhalang pergerakan unit lain — sekarang berbasis saldo penerima ([alur/07](alur/07-batalkan-surat.md)); (3) `BranchScope` di CLI/queue yang bisa mengosongkan hasil ([03 §3b](03-aturan-bisnis.md)); (4) unique index parsial yang tidak ada di MySQL dan soft delete yang menahan nomor seri ([02](02-model-data.md)); (5) aset yang diserahkan ke karyawan cabang lain — aturan "cabang aset mengikuti pemegang" ([03 §3a](03-aturan-bisnis.md)); (6) barang hilang saat dipegang ([alur/06](alur/06-dilepas.md)); (7) pindahkan ke orang lain kini langsung A→B dengan surat sesuai kenyataan ([alur/04](alur/04-pindahkan-ke-orang-lain.md)); (8) status/pemegang barang massal yang menyesatkan — UI memakai ringkasan terhitung ([03 §3](03-aturan-bisnis.md)); plus tolak terima kiriman, prefix kategori immutable, tanggal transaksi sistem = hari ini, hapus draft, dan 6 skenario uji baru (21–26).

## Cara memakai
Letakkan folder ini di `docs/acuan/` dalam repositori Laravel dan rujuk dari `CLAUDE.md`/README. Bila ada keputusan yang berubah, ubah dokumennya dulu, baru kodenya.
