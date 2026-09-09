# 06 — Rencana Eksekusi

Urutan pengerjaan, dampak ke kode yang ada, dan cakupan pengujian.

## K3 — Nasib jalur penerimaan yang sekarang — SUDAH DIKERJAKAN

Setelah Goods Receipt aktif, akan ada tiga cara memasukkan aset:

| Jalur                                                       | Nasib                      |
| ----------------------------------------------------------- | -------------------------- |
| Menu**Pembelian** (`AssetService::receivePurchase`) | ditutup, sisakan arsip     |
| **Impor Aset** (`AssetImporter`)                    | tetap dibuka               |
| **Goods Receipt**                                     | jalur utama pembelian baru |

**Menu Pembelian** disembunyikan tombol Buat dan Tambah Unit-nya; daftarnya
tetap bisa dibuka untuk melihat pembelian lama. Kalau dibiarkan terbuka, admin
punya dua tombol yang sama-sama menerima barang — satu tercatat hutangnya, satu
tidak — dan yang lebih cepat akan selalu menang.

**Impor Aset tetap dibuka.** Fungsinya memasukkan inventaris lama dari
spreadsheet, bukan mencatat pembelian baru. Tidak berbenturan dengan alur PO.

Pembelian mendadak yang tidak sempat dibuatkan PO ditangani lewat **GR tanpa
PO**, bukan lewat menu Pembelian. Satu pintu masuk, tetap fleksibel.

## Urutan migrasi

Aplikasi belum dipakai produksi dan seluruh datanya simulasi, jadi migrasi boleh
ditambahkan sebagai berkas baru tanpa perlu memikirkan pemindahan data. Berkas
migrasi lama tidak perlu disentuh kecuali untuk `purchase_batches`.

```
1. payment_terms
2. vendors                      + npwp, email, payment_term_id
3. purchase_orders
4. purchase_order_items
5. goods_receipts
6. goods_receipt_items
7. purchase_batches             + goods_receipt_id
8. purchase_invoices
9. purchase_invoice_receipts    (bila K4 memilih banyak GR per faktur)
10. vendor_payments
11. vendor_payment_allocations  (bila K5 memilih banyak faktur per pembayaran)
```

Urutan ini mengikuti ketergantungan foreign key. Nomor 7 harus setelah 5 karena
menunjuk `goods_receipts`.

## Tahapan pengerjaan — selesai

Dikerjakan berlapis, tiap lapis diuji sebelum lanjut. Seluruh empat tahap sudah
dijalankan; suite pengujian naik dari 56 menjadi 113 tes.

### Tahap A — Master dan pesanan

Migrasi 1–4, model, relasi, `MasterSeeder` untuk `payment_terms`, Filament
Resource untuk Syarat Pembayaran dan Purchase Order beserta alur persetujuannya.

Selesai bila: PO bisa dibuat, disetujui, dan nomornya terbit.

### Tahap B — Penerimaan dan pembuatan aset

Migrasi 5–7, `GoodsReceiptService`, Filament Resource untuk GR beserta
pengisian serial per unit.

Ini lapis paling berisiko karena menyentuh pembuatan aset. Selesai bila: GR
disetujui menghasilkan unit aset lengkap dengan harga dan garansi, dan unit itu
langsung bisa masuk surat serah terima.

### Tahap C — Tagihan dan pembayaran

Migrasi 8–11, `PurchaseInvoiceService`, `VendorPaymentService`, dua Filament
Resource, dan laporan Hutang Vendor.

Selesai bila: faktur bisa dicatat, dibayar sebagian dan penuh, dan status
berpindah dengan benar.

### Tahap D — Penutupan jalur lama dan dokumentasi

Menu Pembelian jadi arsip, panduan pengguna dan README diperbarui, pemeriksaan
baru ditambahkan ke halaman Pemeriksaan Data.

## Dampak ke kode yang ada

Yang **berubah**:

| Berkas                           | Perubahan                             |
| -------------------------------- | ------------------------------------- |
| `MasterSeeder`                 | tambah`payment_terms`               |
| `VendorForm`, `VendorsTable` | tambah NPWP, email, syarat bayar      |
| `purchase_batches` migrasi     | tambah`goods_receipt_id` nullable   |
| `PurchaseBatchResource`        | tombol Buat dan Tambah Unit dimatikan |
| `DataCheckService`             | tambah pemeriksaan hutang             |
| `DemoDataSeeder`               | alur pembelian lewat PO → GR         |
| Panduan pengguna, README         | bab pengadaan                         |

Yang **tidak tersentuh**, berkat keputusan K1 dan K2:

```
Asset, AssetService, StockCalculator, TransactionService,
HandoverService, HandoverCancellation, BranchTransferService, ServiceService,
seluruh laporan, seluruh ekspor, StatsOverview, AssetImporter
```

Kelima belas berkas yang membaca `purchaseBatch` tetap bekerja apa adanya,
karena GR mengisi tabel yang sama.

## Pengujian — hasil akhir

Suite naik dari 56 menjadi **113 tes, 235 asersi**. Rinciannya:

| Berkas | Tes |
|---|---|
| `PurchaseOrderTest` | 12 |
| `GoodsReceiptTest` | 15 |
| `PayableTest` | 18 |
| `PanelAccessTest` | bertambah 12 halaman baru |

Yang paling penting dijaga:

- Unit hasil GR punya harga, tanggal beli, dan garansi yang benar — ini yang
  menjaga laporan nilai aset tetap jujur
- GR ditolak bila serial kurang, kembar, atau sudah terdaftar
- Terima sebagian menaikkan PO ke `partial_receipt`
- Unit hasil GR langsung bisa masuk surat serah terima — ini yang membuktikan
  modul lama dan baru tersambung
- Alokasi pembayaran tidak boleh melebihi sisa faktur

Halaman baru ditambahkan ke `PanelAccessTest::halamanUtama()` supaya ikut diuji
bisa dibuka.

## Yang sengaja tidak masuk Fase 1

- Jurnal umum dan akuntansi berpasangan
- Uang muka dan kelebihan bayar vendor
- Retur pembelian
- Mata uang selain rupiah
- Barang habis pakai dan ledger kuantitasnya (lihat [01-persediaan.md](01-persediaan.md))
- Batas nilai persetujuan per pengguna atau cabang
- Permintaan pembelian (purchase requisition) sebelum PO

Semuanya bisa ditambahkan belakangan tanpa membongkar struktur ini.

## Keputusan — seluruhnya sudah diambil

| Kode | Pertanyaan | Keputusan |
| --- | --- | --- |
| K1 | Barang non-serial per unit atau per kuantitas? | **per unit**; tabel stok gugur |
| K2 | Harga aset baru dari mana? | **GR menulis `purchase_batches`** |
| K3 | Menu Pembelian ditutup? | **ya**, jadi Arsip Pembelian |
| K4 | Satu faktur boleh mencakup banyak GR? | **ya**, `purchase_invoice_receipts` |
| K5 | Satu pembayaran boleh melunasi banyak faktur? | **ya**, `vendor_payment_allocations` |
| K6 | Siapa yang menyetujui PO? | **admin pusat**, dan bukan pengajunya sendiri |

## Yang muncul di luar rancangan awal

Tiga hal yang baru ketahuan saat dikerjakan:

**`document_counters` perlu kolom `series`.** Tanpa itu nomor PO dan nomor surat
serah terima berebut deret yang sama. Rinciannya di
[02-pengadaan.md](02-pengadaan.md).

**`goods_receipt_items` perlu `unit_price` dan `warranty_months`.** Penerimaan
tanpa pesanan tidak punya sumber harga; tanpa kolom ini unitnya akan bernilai
Rp 0 — persis masalah yang K2 hindari.

**Nama indeks unik harus ditulis pendek.** Nama otomatis Laravel untuk
`purchase_invoice_receipts` mencapai 68 karakter, melewati batas 64 milik MySQL.
