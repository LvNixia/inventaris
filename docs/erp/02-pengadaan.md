# 02 — Pengadaan

Dokumen pemesanan ke vendor, beserta master pendukungnya.

> **Status: sudah dibangun.** Migrasi
> [`2026_09_10_000001_create_procurement_tables.php`](../../database/migrations/2026_09_10_000001_create_procurement_tables.php),
> model, [`PurchaseOrderService`](../../app/Services/PurchaseOrderService.php),
> dua Filament Resource, dan 12 pengujian. Rincian di bawah menggambarkan yang
> benar-benar ada di kode.

## Penomoran dokumen — perubahan yang tidak ada di rencana awal

`document_counters` semula punya satu deret nomor per cabang per bulan, dipakai
surat serah terima. Bila PO memakai deret yang sama, nomor keduanya akan
berselang-seling dan sulit dibaca.

Karena itu tabelnya diberi kolom seri:

```
[MODIFY] document_counters
  + series  string(20), default 'handover'
  unique(branch_id, series, year, month)      menggantikan unique lama
```

`DocumentNumberGenerator::generate()` menerima parameter ketiga berisi seri,
dengan awalan berbeda per jenis dokumen:

| Seri | Awalan | Contoh |
|---|---|---|
| `handover` | `IDS-IT` | `IDS-IT/JKT/2026/09/01` |
| `purchase_order` | `PO` | `PO/JKT/2026/09/01` |
| `goods_receipt` | `GR` | menyusul Tahap B |
| `payment` | `PAY` | menyusul Tahap C |

Awalan lama untuk serah terima tidak berubah, jadi surat yang sudah terbit tetap
terbaca sama.

## `payment_terms` (baru)

Master syarat pembayaran. Dipakai vendor sebagai bawaan, dan disalin ke tiap PO
supaya syarat lama tidak ikut berubah saat masternya diedit.

```
id
code           string, unique          net30, cod, cbd
name           string                  "Net 30 Hari"
days           integer, default 0      jumlah hari jatuh tempo
is_active      boolean, default true
is_system      boolean, default false
sort_order     integer
timestamps
```

Isi awal lewat `MasterSeeder`, mengikuti pola master lain yang sudah ada:

| code | name | days |
|---|---|---|
| `cod` | Bayar di Tempat | 0 |
| `cbd` | Bayar di Muka | 0 |
| `net7` | Net 7 Hari | 7 |
| `net14` | Net 14 Hari | 14 |
| `net30` | Net 30 Hari | 30 |
| `net45` | Net 45 Hari | 45 |

## `vendors` (ubah)

Tabel ini **sudah punya** `address`, `contact`, dan `phone`. Rencana awal
menambahkan `address`, `contact_person`, dan `phone` — tiga dari lima kolom itu
akan berduplikasi dengan yang sudah dipakai seeder dan form.

Kolom sekarang:

```
name, type, contact, phone, address, notes, is_active, timestamps
```

Yang benar-benar baru:

```
[MODIFY] vendors
  + npwp             string nullable
  + email            string nullable
  + payment_term_id  FK payment_terms nullable
```

`contact` yang sudah ada dipakai apa adanya sebagai nama narahubung; tidak perlu
`contact_person`.

## `purchase_orders` (baru)

Header pesanan.

```
id
po_number          string, unique, nullable    dibuat saat PO disetujui
branch_id          FK branches
vendor_id          FK vendors
payment_term_id    FK payment_terms nullable   disalin dari vendor, boleh diubah
po_date            date
expected_date      date nullable
status             enum: draft, pending_approval, approved,
                         partial_receipt, completed, cancelled
subtotal           decimal(15,2), default 0
tax                decimal(15,2), default 0
total              decimal(15,2), default 0
notes              text nullable
submitted_by       FK users nullable
submitted_at       timestamp nullable
approved_by        FK users nullable
approved_at        timestamp nullable
cancelled_at       timestamp nullable
cancel_reason      text nullable
created_by         FK users nullable
timestamps

index(branch_id, po_date)
index(vendor_id)
index(status)
```

Catatan:

- `po_number` menyusul saat disetujui, mengikuti pola `handover_documents` yang
  nomornya baru terbit saat surat diterbitkan. Penomorannya memakai
  `DocumentNumberGenerator` yang sudah ada, berjalan per cabang per bulan.
- Nilai `subtotal`, `tax`, dan `total` disimpan, bukan dihitung ulang tiap
  tampil, supaya PO lama tidak berubah nilainya saat harga barang diperbarui.

## `purchase_order_items` (baru)

```
id
purchase_order_id  FK purchase_orders, cascade on delete
product_id         FK products
quantity           integer                     jumlah dipesan
unit_price         decimal(15,2)
tax_percent        decimal(5,2), default 0     pajak per baris
total_price        decimal(15,2)               quantity x unit_price
warranty_months    integer nullable           dipakai saat penerimaan
notes              string nullable
timestamps

index(purchase_order_id)
index(product_id)
```

`tax_percent` ditaruh di baris, bukan hanya total di header seperti rencana
awal. Alasannya satu PO sering memuat barang kena PPN dan tidak sekaligus;
tanpa ini pajaknya tidak bisa dipertanggungjawabkan per barang.

Jumlah yang sudah diterima **tidak disimpan** di sini. Itu dihitung dari
`goods_receipt_items` yang menunjuk baris ini, supaya tidak ada angka yang harus
dijaga tetap cocok:

```php
public function getReceivedQuantityAttribute(): int
{
    return (int) $this->goodsReceiptItems()->count();
}
```

## Status PO dan perpindahannya

```
draft ──▶ pending_approval ──▶ approved ──▶ partial_receipt ──▶ completed
  │              │                 │
  └──────────────┴─────────────────┴──────────▶ cancelled
```

| Status | Arti | Boleh diubah? |
|---|---|---|
| `draft` | Masih disusun | ya |
| `pending_approval` | Menunggu persetujuan | tidak |
| `approved` | Disetujui, boleh dikirim ke vendor | tidak |
| `partial_receipt` | Sebagian barang sudah diterima | tidak |
| `completed` | Seluruh barang diterima | tidak |
| `cancelled` | Dibatalkan | tidak |

`partial_receipt` dan `completed` ditetapkan sistem saat Goods Receipt disetujui,
bukan dipilih manusia.

PO yang sudah punya penerimaan tidak boleh dibatalkan — sama seperti aturan
pembatalan surat serah terima yang sudah ada.

## Siapa yang menyetujui — K6

**Hanya `admin_pusat`.** Batas nilai per cabang bisa ditambahkan belakangan
tanpa membongkar bentuk tabel.

Ditambah pemisahan tugas: **yang mengajukan tidak bisa menyetujui pengajuannya
sendiri**, walaupun ia admin pusat. Pemeriksaannya membandingkan `submitted_by`
dengan pengguna yang menyetujui, bukan `created_by` — karena PO bisa dibuat
seseorang lalu diajukan orang lain.

## Model dan relasi

```php
PaymentTerm    hasMany vendors, hasMany purchaseOrders

Vendor         belongsTo paymentTerm
               hasMany purchaseOrders
               hasMany purchaseInvoices

PurchaseOrder  belongsTo branch, vendor, paymentTerm, createdBy, approvedBy
               hasMany items (PurchaseOrderItem)
               hasMany goodsReceipts

PurchaseOrderItem  belongsTo purchaseOrder, product
                   hasMany goodsReceiptItems
```

## Dampak ke kode yang ada

- `VendorForm` dan `VendorsTable` bertambah NPWP, email, dan syarat bayar.
- `Vendor` model: sebelumnya tanpa relasi sama sekali, kini punya `paymentTerm`,
  `purchaseOrders`, `purchaseBatches`, dan `assetServices`.
- `MasterSeeder` mengisi enam syarat pembayaran bawaan.
- `DocumentNumberGenerator` menerima parameter seri; pemanggilan lama untuk
  surat serah terima tetap bekerja karena serinya punya nilai bawaan.
- `document_counters` bertambah kolom `series`.

Modul aset, serah terima, servis, mutasi, dan laporan tidak tersentuh.

## Pengujian — sudah ada

[`tests/Unit/PurchaseOrderTest.php`](../../tests/Unit/PurchaseOrderTest.php),
12 pengujian:

- Nilai PO dihitung dari barisnya termasuk pajak per baris
- Pengajuan mengunci isi PO
- PO kosong tidak bisa diajukan
- Nomor PO baru terbit saat disetujui, tidak sebelumnya
- Nomor PO berurutan per cabang per bulan
- Pengaju tidak bisa menyetujui PO-nya sendiri
- Admin cabang tidak bisa menyetujui
- PO draf tidak bisa langsung disetujui
- Pengembalian ke draf membuka kunci isi
- Pembatalan wajib menyertakan alasan
- PO yang sudah dibatalkan tidak bisa dibatalkan lagi
- PO dengan penerimaan sebagian tidak bisa dibatalkan

Ditambah empat halaman baru pada `PanelAccessTest`: daftar PO, buat PO, syarat
pembayaran, dan vendor.
