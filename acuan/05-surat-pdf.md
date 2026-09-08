# 05 — Surat Serah Terima & Bukti Pengembalian (PDF)

## 1. Surat serah terima
A4 portrait. Margin dan logo kop mengikuti template resmi perusahaan (file logo: keputusan terbuka). Render dengan DomPDF dari `resources/views/pdf/handover.blade.php`, sumber data = snapshot di `handover_documents` + `handover_items` (bukan data aset saat ini).

```
[Kop] INDOSURTA
      Surveying & Mapping Equipment
      Sales, Service, Rental, & Calibration

SURAT SERAH TERIMA BARANG
Nomor: IDS-IT/JKT/2026/09/01

Pada hari ini, {Hari} tanggal {D} bulan {Bulan} tahun {YYYY}, telah dilakukan serah terima barang antara:

1. Pihak Pertama (Yang Menyerahkan)
   Nama       : …
   Jabatan    : …
   Departemen : …
2. Pihak Kedua (Yang Menerima)
   Nama / Jabatan / Departemen

Dengan ini Pihak Pertama menyerahkan kepada Pihak Kedua barang-barang sebagai berikut:
| No | Nama Barang | Nomor Seri | Spesifikasi (bullet) | Jumlah | Kondisi | Keterangan (aksesoris; "Dipakai oleh: …") |

Barang-barang tersebut telah diperiksa bersama dan dinyatakan dalam kondisi baik. Setelah penandatanganan surat ini, seluruh tanggung jawab atas barang tersebut beralih kepada Pihak Kedua.
Demikian surat serah terima barang ini dibuat untuk dipergunakan sebagaimana mestinya.

Pihak Pertama                          Pihak Kedua
(nama)                                 (nama)
(jabatan)                              (jabatan)

              Mengetahui
              (nama)
              (jabatan)
```

Ketentuan render:
- Hari/bulan bahasa Indonesia (Carbon `locale('id')`).
- Nomor seri kosong untuk barang tak berseri (bukan "-").
- Blok tanda tangan dan tiap baris tabel `page-break-inside: avoid`; `thead` berulang tiap halaman (`display: table-header-group`).
- Footer "Hal. x dari y"; nomor surat di header halaman ≥ 2.
- Font DejaVu Sans (bawaan DomPDF; mendukung •).
- Disimpan ke `storage/app/private/handovers/{tahun}/{uuid}.pdf`; nama unduhan = nomor surat dengan `/` → `_`.
- Surat batal: watermark diagonal "DIBATALKAN" + baris alasan & tanggal pembatalan di bawah nomor.
- Preview draft: PDF yang sama dengan tulisan "DRAFT — belum bernomor" di tempat nomor; tidak disimpan.

## 2. Bukti pengembalian
Satu halaman, `return-receipt.blade.php`, dibuat dari transaksi arah `in` (satu atau beberapa transaksi yang dibuat dalam satu aksi "Tarik kembali"/"Tarik semua"):

```
BUKTI PENGEMBALIAN BARANG
Nomor referensi: RTN-{branch}/{YYYY}/{MM}/{id transaksi pertama}
Tanggal: …

Dikembalikan oleh : {from_employee}  (jabatan, divisi)
Diterima oleh     : {to_employee}    (jabatan, divisi)

| No | Kode Aset | Nama Barang | Nomor Seri | Jumlah | Kondisi saat diterima | Aksesoris ikut kembali | Catatan |

Yang mengembalikan            Yang menerima
(nama)                        (nama)
```
Tidak memakai counter nomor surat; nomor referensi cukup dari id transaksi.

## 3. Tanda terima servis
Satu halaman, `service-receipt.blade.php`, dari `asset_services` saat barang diserahkan ke vendor/IT (versi "Penyerahan") dan saat diambil kembali (versi "Pengambilan"): barang (kode, nama, serial), keluhan/pekerjaan, kelengkapan yang ikut, nama & kontak vendor, tanggal, estimasi; pada versi pengambilan ditambah hasil, biaya, garansi servis. Nomor referensi `SVC-{branch}/{YYYY}/{id catatan}`.
