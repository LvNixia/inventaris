# 03 — Antarmuka

## Bilah sisi

25 modul untuk tim beranggota beberapa orang. Sembilan di antaranya data acuan
yang diisi sekali lalu nyaris tidak pernah disentuh lagi:

| Grup | Isi | Frekuensi pakai |
|---|---|---|
| Manajemen Aset | Barang, Aset, Servis, Transaksi | setiap hari |
| Dokumen | Serah Terima | setiap minggu |
| Pengadaan | Pesanan, Penerimaan, Faktur, Pembayaran, Vendor, Batch | setiap minggu |
| Organisasi | Cabang, Divisi, Jabatan, Karyawan | sesekali |
| Referensi | Kategori, Merek, Kondisi, Status, Alasan Pelepasan, Jenis Servis, Hasil Servis, Jenis Lampiran, Syarat Bayar | hampir tidak pernah |
| Pengaturan | Pengguna | hampir tidak pernah |

### Usulan

**Referensi hanya untuk Admin Pusat.** Sembilan modul hilang dari bilah sisi
sebagian besar pengguna. Ini datang gratis bersama [otorisasi](01-otorisasi.md)
— tidak ada kode menu yang perlu diubah.

**Grup Referensi ditutup secara bawaan.** Filament mendukung
`->collapsed()` pada grup navigasi. Isinya tetap ada, tetapi tidak memakan
layar.

Yang **tidak** diusulkan: menggabungkan modul acuan menjadi satu halaman
serbaguna. Tabelnya berbeda-beda, dan halaman gabungan seperti itu selalu lebih
sulit dipakai daripada sembilan halaman sederhana yang jarang dibuka.

## Modul turunan

Dua modul menampilkan data yang tidak pernah diketik manusia:

**Batch Pembelian** lahir dari penerimaan barang. Mengubahnya lewat formulir
berarti mengubah harga perolehan unit yang sudah terlanjur tercatat, tanpa
jejak siapa pun.

**Transaksi Aset** adalah catatan riwayat. Setiap barisnya dibuat service saat
aset bergerak.

### Usulan

Jadikan keduanya **baca-saja**: hapus tombol Buat, Ubah, dan Hapus; sisakan
tampilan dan penyaring. Keduanya tetap berguna untuk ditelusuri — yang dicabut
hanya kemampuan mengarangnya.

Kalau harga batch memang perlu diperbaiki, jalurnya membatalkan penerimaan lalu
mencatat ulang. Itu memang lebih repot, dan memang seharusnya begitu.

## Nilai faktur

Tiga kolom uang di formulir faktur — Subtotal, PPN, Total — sekarang semuanya
terisi sendiri dari penerimaan dan pesanan di baliknya. Yang tersisa bagi
pengguna hanyalah memutuskan apakah angka itu cocok dengan kertas dari vendor.

Tiga kolom yang boleh diketik untuk pekerjaan yang isinya "periksa, biasanya
cocok" adalah undangan salah ketik.

### Usulan

Tampilkan ketiganya sebagai ringkasan baca-saja, dengan satu tombol
**Sesuaikan Nilai** yang membukanya untuk diubah bila memang berbeda.

```
Subtotal      Rp 20.000.000     dari 2 penerimaan
PPN           Rp  2.200.000     11% dari baris pesanannya
─────────────────────────────
Total         Rp 22.200.000     [ Sesuaikan Nilai ]
```

Perbedaan angka jadi tindakan sadar, bukan kecelakaan. Rincian per penerimaan
sudah ada di atasnya, jadi angka ini bisa ditelusuri tanpa berpindah halaman.

## Yang sudah benar dan tidak perlu diapa-apakan

**Urutan menu Pengadaan** sudah mengikuti frekuensi pakai: Pesanan, Penerimaan,
Faktur, Pembayaran.

**Tombol lintas dokumen** — Buat Penerimaan, Buat Faktur, Bayar — sudah
menghilangkan pengetikan ulang antar tahap.

**Halaman Panduan** panjang, tetapi panduan memang dibaca sekali lalu dicari
lagi saat lupa. Memecahnya menjadi banyak halaman justru membuat pencarian
lebih sulit.
