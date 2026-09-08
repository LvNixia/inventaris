# 13 — Export & Backup Workbook

**Siapa:** semua peran, sesuai cakupannya (viewer tanpa kolom harga/vendor/faktur).

## Jenis

| Export | Isi | Cakupan |
|---|---|---|
| Register aset | semua kolom + turunan (umur, total nilai, status garansi, pemegang, pemakai, status, sisa) | filter & pencarian aktif + scope cabang |
| Riwayat transaksi | ledger + pemakai + cabang + nomor surat + pembuat | idem |
| Daftar surat | nomor, tanggal, para pihak, jumlah item, status | idem |
| Sebaran pemegang | satu baris per (aset, pemegang, saldo) | idem |
| Dashboard | angka ringkasan + tabel sebaran | scope cabang |
| PDF surat | per surat, atau ZIP per rentang tanggal | scope cabang |
| Backup workbook | satu `.xlsx`: sheet Master, Aset, Riwayat (nilai saja) | admin pusat |

## Langkah di UI
Tombol **Export** di header setiap daftar → pilih format (`.xlsx` default, `.csv` UTF-8 BOM) → unduh langsung (≤ 5.000 baris) atau notifikasi "file siap" (queue) → unduh dari halaman Export.

## Proses di server
- Query = query tabel yang sedang tampil (filter, pencarian, sort) + `BranchScope` + kolom yang diizinkan peran. Tidak ada query terpisah untuk export, supaya angka di layar dan di file selalu sama.
- Nama file `{jenis}_{kode cabang|semua}_{YYYYMMDD_HHmm}.xlsx`.
- File hasil queue disimpan di disk privat 24 jam lalu dihapus (`exports:prune`).

## Backup workbook
- Perintah `backup:workbook` dijalankan scheduler mingguan (dan manual dari UI admin pusat).
- Tiga sheet: **Master** (semua tabel master), **Aset** (kolom = template import aset + kode + kolom turunan), **Riwayat** (kolom = template import transaksi + id + tipe). Nilai saja, tanpa rumus.
- Disimpan ke `storage/app/private/backups/workbook_{YYYYMMDD}.xlsx`, ikut dalam backup harian ke luar server.
- Fungsi: tim yang terbiasa dengan spreadsheet tetap bisa membaca data, dan menjadi jalur keluar bila aplikasi bermasalah.
