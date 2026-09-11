# 04 — Kode

Bagian ini perawatan, bukan perbaikan. Tidak ada cacat yang ditutup di sini —
yang dikurangi adalah tempat yang harus diingat saat sesuatu berubah.

## Duplikasi yang layak dicabut

### Label status berserakan di tabel

```
GoodsReceiptsTable::STATUS      draft, received, cancelled
PurchaseInvoicesTable::STATUS   unpaid, partial, paid, cancelled
PurchaseOrdersTable::STATUS     draft, pending_approval, approved, ...
VendorPayment::METODE           transfer, tunai, giro
```

Tiga di antaranya tinggal di kelas tabel, satu di model. Statusnya milik
dokumennya, bukan milik tampilannya — laporan dan notifikasi yang butuh label
yang sama harus mengimpor kelas tabel untuk mendapatkannya.

**Usulan:** pindahkan ke enum berlabel, seperti `App\Enums\Role` yang sudah ada
dan sudah mengimplementasikan `HasLabel`. Filament membaca label dan warnanya
langsung dari enum, sehingga `formatStateUsing` dan `color` di tabel ikut
hilang.

Empat enum: `StatusPesanan`, `StatusPenerimaan`, `StatusFaktur`,
`MetodePembayaran`.

### Pembungkus notifikasi yang sama

`GoodsReceiptsTable` dan `PurchaseOrdersTable` sama-sama punya:

```php
protected static function jalankan(callable $aksi, string $judulSukses): void
```

Isinya identik: jalankan, tangkap `Exception`, tampilkan sebagai notifikasi
merah yang menetap. Pola ini akan diulang lagi setiap kali ada tabel dengan
tombol yang memanggil service.

**Usulan:** satu concern, `App\Filament\Concerns\MenjalankanAksiService`.

### Aturan persetujuan ganda

Dibahas di [02-kebijakan-pengadaan.md](02-kebijakan-pengadaan.md#aturan-yang-tertulis-dua-kali).
Ini satu-satunya duplikasi di daftar ini yang bisa berakibat salah, bukan
sekadar merepotkan.

## Yang sebaiknya dibiarkan

**`AssetSelect`** sudah menjadi pabrik bersama untuk dropdown aset. Polanya
benar; tidak perlu diperluas menjadi kerangka kerja untuk komponen lain sebelum
ada pemakai kedua yang nyata.

**Service per dokumen** — `PurchaseOrderService`, `GoodsReceiptService`,
`PurchaseInvoiceService`, `VendorPaymentService` — terlihat seperti empat kelas
yang mirip. Menggabungkannya menjadi satu `ProcurementService` akan menghasilkan
satu berkas besar yang setiap perubahannya menyentuh empat alur sekaligus.
Biarkan terpisah.

**Enam kelas laporan** berbagi `BaseReportPage`. Pengulangan yang tersisa di
tiap laporan adalah kolom dan penyaringnya — memang berbeda-beda, memang harus
ditulis.

**Migrasi yang diedit di tempat.** Selama aplikasi belum produksi, ini lebih
bersih daripada menumpuk migrasi tambal. Begitu produksi dimulai, kebiasaan itu
harus berhenti — dan itu perlu ditulis di README sebagai garis yang jelas.

## Urutan

Kerjakan enum lebih dulu; ia menyentuh tabel yang sama dengan pekerjaan
antarmuka, jadi keduanya sebaiknya berdekatan. Concern notifikasi dan aturan
persetujuan berdiri sendiri dan bisa kapan saja.

Seluruh bagian ini bisa ditunda tanpa risiko. Kalau harus memilih antara ini dan
[otorisasi](01-otorisasi.md), pilih otorisasi.
