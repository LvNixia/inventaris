# Rancangan Perbaikan & Penyederhanaan

Dokumen ini memecah rencana menjadi satu berkas per bidang. Belum ada kode yang
ditulis; ini spesifikasi yang harus disetujui lebih dulu.

| Berkas | Isi |
|---|---|
| [01-otorisasi.md](01-otorisasi.md) | Siapa boleh melakukan apa. Lubang terbesar yang masih terbuka. |
| [02-kebijakan-pengadaan.md](02-kebijakan-pengadaan.md) | Batas kumulatif, vendor yang hilang, aturan yang tertulis dua kali |
| [03-antarmuka.md](03-antarmuka.md) | Menu, modul turunan, formulir yang isinya sudah otomatis |
| [04-kode.md](04-kode.md) | Duplikasi yang layak dicabut, dan yang sebaiknya dibiarkan |

## Keadaan sekarang

Aplikasi sudah berjalan utuh: rantai pengadaan penuh, pelacakan aset per unit,
6 laporan, impor XLSX, dan Pemeriksaan Data. 142 tes lulus, Pint bersih,
Pemeriksaan Data 0 temuan.

Yang tersisa bukan fitur yang kurang, melainkan **satu lubang keamanan** dan
**beban antarmuka yang tidak sebanding** dengan cara aplikasi ini benar-benar
dipakai: belanja lewat e-commerce, sesekali offline, tim IT kecil.

## Urutan yang disarankan

Kerjakan dari atas. Bagian pertama menahan risiko nyata; sisanya menghemat
waktu setiap hari.

| # | Pekerjaan | Kenapa sekarang | Perkiraan |
|---|---|---|---|
| 1 | [Otorisasi](01-otorisasi.md) | Peninjau bisa menghapus aset dan mencatat pembayaran | Sedang |
| 2 | [Vendor pada penerimaan](02-kebijakan-pengadaan.md#vendor-yang-hilang) | Jejak "beli di mana" hilang diam-diam | Kecil |
| 3 | [Menu & modul turunan](03-antarmuka.md) | 25 modul di bilah sisi untuk tim beranggota beberapa orang | Kecil |
| 4 | [Aturan persetujuan tunggal](02-kebijakan-pengadaan.md#aturan-yang-tertulis-dua-kali) | Dua salinan aturan yang bisa berbeda tanpa ketahuan | Kecil |
| 5 | [Nilai faktur otomatis](03-antarmuka.md#nilai-faktur) | Tiga kolom uang yang isinya sudah dihitung sistem | Kecil |
| 6 | [Batas kumulatif](02-kebijakan-pengadaan.md#batas-kumulatif) | Pemecahan pesanan menembus batas persetujuan | Sedang |
| 7 | [Duplikasi kode](04-kode.md) | Perawatan, bukan perbaikan | Kecil |

Nomor 6 bergantung pada keputusan kebijakan Anda, bukan pada kode. Kalau timnya
kecil dan saling tahu, boleh dilewati.

## Yang sengaja tidak diusulkan

**Menghapus modul Faktur dan Pembayaran.** Sudah dibahas: begitu ada satu
pembelian bertempo, modulnya harus dibangun ulang, dan pelacakan hutangnya
sekarang sudah benar termasuk penjagaan kelebihan bayar.

**Menyederhanakan model tiga lapis.** `products → purchase_batches → assets`
terlihat berlapis, tetapi lapisan itulah yang menghapus seluruh aritmetika stok
beserta bugnya. Menggabungkannya berarti mengembalikan masalah yang sudah
selesai.

**Mengurangi Pemeriksaan Data.** Delapan pemeriksaan itu murah dan menangkap
hal yang tidak bisa dicegah formulir.
