# 08 — Keamanan & Non-fungsional

| Area | Ketentuan |
|---|---|
| File | PDF & lampiran di disk privat (`storage/app/private`), nama UUID; unduh lewat route ber-autentikasi + policy; tidak ada URL publik permanen |
| Autentikasi | Email + password ≥ 12 karakter, rate limit 5/menit, sesi 8 jam; 2FA opsional untuk admin pusat |
| Otorisasi | Policy untuk semua model; `BranchScope` di Model, bukan controller |
| Audit | activity_log create/update/delete aset, transaksi, surat, pengguna, master; tidak bisa dihapus dari UI |
| Integritas | Operasi stok memakai `DB::transaction()` + `lockForUpdate()` pada aset & counter; cache `qty_*`/`current_*` hanya ditulis `StockCalculator`; `stock:recompute` menghitung ulang semua & melaporkan selisih |
| Konfigurasi | `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`; tanggal disimpan `date`, ditampilkan `d/m/Y` |
| Queue & scheduler | `QUEUE_CONNECTION=database`; `queue:work` via Supervisor atau cron `--stop-when-empty` tiap menit; `schedule:run` tiap menit untuk backup, pengingat garansi, `drafts:expire` |
| Backup | Dump DB harian + `storage/app/private` ke luar server; uji restore sebelum go-live dan tiap 3 bulan |
| Ekstensi PHP | `pdo_mysql`, `mbstring`, `gd`, `zip`, `xml`, `intl`, `fileinfo` |
| Batas | Lampiran ≤ 5 MB (`jpg/png/pdf`); import ≤ 10 MB; export > 5.000 baris lewat queue |
| Notifikasi (opsional) | Email mingguan: garansi ≤ 90 hari, draft kedaluwarsa, pemeriksaan data ≠ 0; SMTP biasa |
| Bahasa | UI bahasa Indonesia lewat `lang/id/*.php`; tidak ada label hardcode di view |
