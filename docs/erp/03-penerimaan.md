# 03 — Penerimaan Barang

Submodul terpenting: di sinilah unit aset lahir. Menjawab **K2**, sumber harga
aset baru.

> **Status: sudah dibangun.** Migrasi
> [`2026_09_10_000002_create_goods_receipt_tables.php`](../../database/migrations/2026_09_10_000002_create_goods_receipt_tables.php),
> model, [`GoodsReceiptService`](../../app/Services/GoodsReceiptService.php),
> Filament Resource, dan 15 pengujian.

## `goods_receipts` (baru)

```
id
gr_number                  string, unique, nullable   terbit saat disetujui
purchase_order_id          FK purchase_orders nullable
branch_id                  FK branches
vendor_id                  FK vendors
receipt_date               date
delivery_document_number   string nullable            nomor surat jalan
status                     enum: draft, received, cancelled
notes                      text nullable
received_by                FK users nullable
received_at                timestamp nullable
created_by                 FK users nullable
timestamps

index(branch_id, receipt_date)
index(purchase_order_id)
index(status)
```

`purchase_order_id` dibuat **nullable**. Rencana awal mewajibkannya, padahal
pembelian mendadak, hibah, dan retur vendor tidak punya PO. Membuatnya wajib
akan mendorong orang memakai jalur lain, dan itu justru menghilangkan jejak yang
ingin dibangun. GR tanpa PO tetap tercatat, hanya tidak punya pembanding
pesanan.

`vendor_id` disalin dari PO saat ada, dan diisi manual saat tidak ada.

## `goods_receipt_items` (baru)

Rencana awal memakai satu baris per barang dengan kolom `quantity_received`.
Rancangan ini memakai **satu baris per unit fisik**.

```
id
goods_receipt_id         FK goods_receipts, cascade on delete
purchase_order_item_id   FK purchase_order_items nullable
product_id               FK products
serial_number            string nullable     diisi saat kategori mewajibkan
imei_1                   string nullable
imei_2                   string nullable
condition_id             FK conditions nullable    bawaan: baik
asset_id                 FK assets nullable        diisi saat GR disetujui
notes                    string nullable
timestamps

index(goods_receipt_id)
index(purchase_order_item_id)
```

Alasannya tiga:

**Serialnya butuh tempat.** Rencana awal melarang persetujuan sebelum admin
mengetik serial sebanyak `quantity_received`, tetapi tidak menyediakan kolom
untuk menyimpannya sebelum disetujui. Draft GR harus bisa disimpan setengah jadi
dan dilanjutkan besok.

**Jumlah jadi hasil hitungan, bukan angka yang dijaga.** `quantity_received`
adalah `count()` dari baris ini. Tidak ada kemungkinan jumlah dan daftar serial
berbeda.

**Sejalan dengan model per unit.** Bentuknya sama dengan `assets`, jadi
pembuatan asetnya pemetaan satu ke satu.

Untuk barang tanpa serial, barisnya tetap satu per unit dengan `serial_number`
kosong.

Kolom `unit_price` dan `warranty_months` ikut disimpan di baris penerimaan —
tidak ada di rencana awal. Alasannya GR tanpa pesanan tidak punya sumber harga,
jadi harganya harus bisa diisi manual. Saat GR punya pesanan, keduanya disalin
otomatis dari baris pesanannya.

## Mekanisme saat GR disetujui

Seluruhnya dalam satu transaksi basis data.

```
1. Periksa status GR masih draft
2. Periksa tiap baris:
     - kategori wajib serial → serial_number tidak boleh kosong
     - serial tidak boleh kembar di dalam GR ini
     - serial tidak boleh sudah terdaftar di assets
     - bila punya PO, jumlah terima tidak melebihi sisa pesanan
3. Bila ada yang gagal → tolak seluruhnya, sebutkan baris keberapa
4. Terbitkan gr_number
5. Buat purchase_batches untuk tiap kombinasi produk + harga    (lihat K2)
6. Untuk tiap baris → Asset::create(), simpan id-nya ke goods_receipt_items.asset_id
7. Perbarui status PO: partial_receipt atau completed
8. Tandai GR received
```

Langkah 2 sengaja memeriksa seluruh baris sebelum menulis apa pun, mengikuti
pola `HandoverService::issue()` yang mengumpulkan semua kesalahan lalu
menampilkannya sekaligus. Pengguna melihat semua yang kurang dalam sekali coba,
bukan satu per satu.

Pesan penolakan serial memakai `Product::serialLabel()` yang sudah ada, sehingga
lisensi berbunyi "Kunci Lisensi", bukan "Nomor Seri".

## K2 — Dari mana harga aset baru

`purchase_batches` adalah satu-satunya sumber harga, tanggal beli, faktur, dan
garansi untuk setiap unit. Lima belas berkas membacanya:

```
Asset::getUnitPriceAttribute()        → purchaseBatch->unit_price
Asset::getPurchaseDateAttribute()     → purchaseBatch->purchase_date
Asset::getWarrantyUntilAttribute()    → purchaseBatch->warranty_until
Asset::getInvoiceNumberAttribute()    → purchaseBatch->invoice_number
LaporanPosisiAset::nilaiAktif()       → $asset->unit_price
LaporanMutasi::nilaiPelepasan()       → $asset->unit_price
StatsOverview                         → join purchase_batches, sum unit_price
AssetExportService, BackupWorkbookCommand, AssetsTable, AssetForm,
ProductsTable, DataCheckService, AssetImporter, AssetService
```

Bila GR membuat aset tanpa `purchase_batch_id`, **setiap aset baru bernilai
Rp 0**, tanpa tanggal beli dan tanpa garansi, mulai dari unit pertama. Halaman
Pemeriksaan Data akan menandai semuanya sebagai bermasalah, karena sudah ada
pemeriksaan `unitTanpaBatchPembelian()`.

### Rekomendasi: GR menulis `purchase_batches`

Saat GR disetujui, sistem membuat satu batch per kombinasi produk dan harga di
dalam GR itu:

| Kolom batch | Diisi dari |
|---|---|
| `product_id` | baris GR |
| `branch_id` | GR |
| `vendor_id` | GR |
| `purchase_date` | `receipt_date` |
| `unit_price` | `purchase_order_items.unit_price`, atau diisi manual bila GR tanpa PO |
| `warranty_months` | diisi saat penerimaan |
| `warranty_until` | dihitung dari `receipt_date` + garansi |
| `invoice_number` | dikosongkan dulu, diisi saat faktur vendor masuk |
| `goods_receipt_id` | kolom baru, penanda asal |

```
[MODIFY] purchase_batches
  + goods_receipt_id  FK goods_receipts nullable
```

`purchase_batches` berubah peran: dari tabel yang diisi manusia menjadi
**lapisan biaya yang ditulis sistem**. Kelima belas berkas di atas tidak perlu
disentuh sama sekali.

Alternatifnya — memindahkan accessor biaya ke `purchase_order_items` — lebih
murni secara model, tetapi berarti membongkar ulang seluruh laporan, ekspor,
widget, dan Pemeriksaan Data. Untuk keuntungan yang tidak dirasakan pengguna,
itu tidak sepadan.

### Nilai bawaan saat membuat aset

`assets.condition_id` bersifat NOT NULL dan rencana awal belum menyebutnya.
Bawaannya:

| Kolom aset | Nilai |
|---|---|
| `asset_code` | `AssetCodeGenerator::generate($product->category)` |
| `product_id` | dari baris GR |
| `purchase_batch_id` | batch yang baru dibuat |
| `branch_id` | dari GR |
| `serial_number`, `imei_1`, `imei_2` | dari baris GR |
| `condition_id` | dari baris GR, bawaan `baik` |
| `current_status_id` | `registered` |
| `created_by`, `updated_by` | pengguna yang menyetujui |

Setelah itu unit langsung siap dipakai modul serah terima, servis, mutasi
cabang, dan lampiran — tanpa perubahan apa pun di modul tersebut.

## Pembatalan GR

GR yang sudah `received` hanya boleh dibatalkan bila seluruh aset yang lahir
darinya belum bergerak sama sekali — belum diserahkan, diservis, dipindah, atau
dilepas. Aturannya sama dengan pembatalan impor aset dan pembatalan surat serah
terima yang sudah ada.

Pembatalan menghapus asetnya, menghapus batch pembelian yang jadi yatim, dan
mengembalikan status PO.

## Model dan relasi

```php
GoodsReceipt      belongsTo purchaseOrder, branch, vendor, createdBy, receivedBy
                  hasMany items (GoodsReceiptItem)
                  hasMany purchaseBatches
                  hasOne purchaseInvoice

GoodsReceiptItem  belongsTo goodsReceipt, purchaseOrderItem, product,
                            condition, asset

Asset             belongsTo purchaseBatch          (sudah ada)
PurchaseBatch     belongsTo goodsReceipt           (baru)
```

## Dampak ke kode yang ada

- `purchase_batches` bertambah satu kolom nullable. Tidak ada yang rusak.
- `AssetService::create()` dipakai ulang apa adanya untuk membuat unit.
- Modul serah terima, servis, mutasi, lampiran: tidak tersentuh.

## Pengujian — sudah ada

[`tests/Unit/GoodsReceiptTest.php`](../../tests/Unit/GoodsReceiptTest.php),
15 pengujian:

- GR disetujui melahirkan unit lengkap dengan harga, tanggal beli, dan garansi
- Unit seharga sama berbagi satu batch pembelian
- Unit hasil GR langsung bisa masuk surat serah terima
- Serial kosong pada kategori wajib ditolak, dan **tidak ada unit yang telanjur lahir**
- Lisensi memakai sebutan "Kunci Lisensi" pada pesan penolakan
- Serial kembar di dalam satu GR ditolak
- Serial yang sudah terdaftar di `assets` ditolak
- Seluruh kesalahan dilaporkan sekaligus, bukan satu per satu
- Terima sebagian menaikkan PO ke `partial_receipt`
- Terima penuh menutup PO ke `completed`
- Terima melebihi sisa pesanan ditolak
- GR tanpa PO tetap bisa disetujui
- Pembatalan menghapus unit dan batch, lalu mengembalikan status PO
- Pembatalan ditolak bila unitnya sudah diserahkan
- GR kosong tidak bisa disetujui

Ditambah dua halaman pada `PanelAccessTest`: daftar dan buat penerimaan.
