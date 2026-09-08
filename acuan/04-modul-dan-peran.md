# 04 — Modul & Peran

## 1. Peta modul

| Modul | Halaman | Alur terkait |
|---|---|---|
| Dashboard | ringkasan + sebaran | — |
| Register Aset | daftar, form, detail (riwayat, sebaran pemegang, surat, lampiran, servis, aksi), halaman Servis Berjalan | [01](alur/01-tambah-aset.md), [02](alur/02-terbitkan-surat.md), [03](alur/03-tarik-kembali.md), [04](alur/04-pindahkan-ke-orang-lain.md), [05](alur/05-ubah-status.md), [06](alur/06-dilepas.md), [08](alur/08-pindah-cabang.md), [14](alur/14-servis-upgrade.md) |
| Surat Serah Terima | daftar (draft/terbit/batal), form, preview, PDF | [02](alur/02-terbitkan-surat.md), [07](alur/07-batalkan-surat.md) |
| Transaksi | daftar riwayat, form transaksi manual | [03](alur/03-tarik-kembali.md), [05](alur/05-ubah-status.md), [06](alur/06-dilepas.md), [11](alur/11-koreksi.md) |
| Karyawan | daftar, form, mutasi, nonaktif | [09](alur/09-mutasi-karyawan.md), [10](alur/10-nonaktifkan-karyawan.md) |
| Master & Pengaturan | cabang, divisi, jabatan, karyawan, kategori, merk, kondisi, status aset, alasan dilepas, jenis & hasil servis, vendor, jenis lampiran, pengguna — semuanya tambah/ubah/nonaktif/hapus/gabung | [15](alur/15-kelola-master.md) |
| Import | wizard (unduh template → unggah → dry-run → proses), riwayat batch | [12](alur/12-import.md) |
| Export | tombol di tiap daftar; backup workbook | [13](alur/13-export.md) |
| Pemeriksaan Data | anomali | — |
| Bantuan | panduan singkat per alur (isi = folder `alur/`) | — |

## 1a. Master data
Semua kosakata bisnis adalah master yang dikelola dari UI, dengan aturan hapus = hanya bila belum dipakai, selain itu nonaktifkan; entri bawaan sistem bisa diganti namanya tapi tidak bisa dihapus. Detail: [11-master-data.md](11-master-data.md).

## 2. Register aset
- Daftar: filter kategori, status, cabang, pemegang, pemakai, garansi; pencarian kode/serial/model; badge garansi dan `qty_available = 0`.
- Form: kategori dipilih pertama (menentukan wajib serial & daftar kondisi); pratinjau kode, final saat simpan.
- Detail: info, riwayat, sebaran pemegang (barang massal), surat yang memuatnya, lampiran, tombol aksi.

## 3. Dashboard
Ringkasan: baris aset, unit aktif, servis berjalan (dan yang lewat estimasi), biaya servis tahun ini, (`quantity − qty_writeoff`) dan unit dilepas, total nilai unit aktif, rata-rata umur, belum pernah diserahkan, garansi hampir habis / habis, baris bermasalah, unit siap diserahkan, unit dipegang, unit di gudang. Sebaran per kategori (unit & nilai), status, divisi pemegang, cabang. Semua mengikuti scope cabang. Widget Filament untuk Opsi A.

## 4. Peran

| Peran | Hak |
|---|---|
| Admin pusat | semua cabang; master, pengguna, pembatalan surat, import transaksi, koreksi, penghapusan bila diizinkan |
| Admin cabang | CRUD aset & transaksi cabangnya, terbitkan surat cabangnya, tambah karyawan cabangnya, lihat master, export cabangnya |
| Viewer | hanya baca. Bila `employee_id` terisi → aset yang dipegang/dipakai karyawan satu divisi di cabangnya + surat terkait; bila kosong → seluruh cabangnya. Tidak melihat harga, vendor, faktur |

Matriks aksi:

| Aksi | Admin pusat | Admin cabang | Viewer |
|---|---|---|---|
| Tambah/edit aset | ✔ | ✔ (cabangnya) | — |
| Terbitkan surat | ✔ | ✔ (cabangnya) | — |
| Batalkan surat | ✔ | — | — |
| Tarik kembali / ubah status / dilepas | ✔ | ✔ (cabangnya) | — |
| Buka / selesaikan servis & upgrade | ✔ | ✔ (cabangnya) | — |
| Koreksi | ✔ | — | — |
| Pindah cabang (kirim) | ✔ | ✔ (dari cabangnya) | — |
| Pindah cabang (konfirmasi terima) | ✔ | ✔ (ke cabangnya) | — |
| Mutasi / nonaktifkan karyawan | ✔ | ✔ (cabangnya, nonaktif saja) | — |
| Kelola master (tambah/ubah) | ✔ | ✔ terbatas: karyawan cabangnya, merk, vendor, jabatan | — |
| Hapus / nonaktifkan / gabung master | ✔ | — | — |
| Import master | ✔ | — | — |
| Import aset | ✔ | ✔ (cabangnya) | — |
| Import transaksi / pembaruan aset | ✔ | — | — |
| Export | ✔ | ✔ (cabangnya) | ✔ (tanpa harga) |
| Unduh PDF surat | ✔ | ✔ | ✔ (cakupannya) |

Semua aksi tercatat di activity log (siapa, kapan, nilai lama/baru).
