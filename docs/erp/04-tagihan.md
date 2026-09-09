# 04 — Tagihan Vendor

Pelacakan hutang usaha tanpa jurnal umum.

> **Status: sudah dibangun.** Migrasi
> [`2026_09_10_000003_create_payable_tables.php`](../../database/migrations/2026_09_10_000003_create_payable_tables.php),
> model, [`PurchaseInvoiceService`](../../app/Services/PurchaseInvoiceService.php),
> Filament Resource, laporan Hutang Vendor, dan 18 pengujian bersama submodul
> pembayaran.
>
> **K4 diputuskan: satu faktur boleh mencakup beberapa penerimaan.** Tabel
> penghubung `purchase_invoice_receipts` dibangun.

## `purchase_invoices` (baru)

```
id
invoice_number     string                      nomor dari vendor, bukan dari kita
vendor_id          FK vendors
branch_id          FK branches
invoice_date       date
due_date           date                        dihitung dari payment term
payment_term_id    FK payment_terms nullable
subtotal           decimal(15,2), default 0
tax                decimal(15,2), default 0
total_amount       decimal(15,2), default 0
paid_amount        decimal(15,2), default 0
status             enum: unpaid, partial, paid, cancelled
notes              text nullable
created_by         FK users nullable
timestamps

unique(vendor_id, invoice_number)
index(vendor_id, status)
index(due_date)
```

`invoice_number` berasal dari vendor, jadi tidak dibuat sistem dan tidak unik
secara global — dua vendor bisa punya nomor faktur sama. Karena itu keunikannya
dipasangkan dengan `vendor_id`.

## Satu faktur mencakup berapa penerimaan?

Rencana awal memakai `goods_receipt_id` tunggal di header faktur, artinya satu
faktur hanya boleh mencakup satu penerimaan.

Kenyataannya vendor sering menagih beberapa pengiriman sekaligus dalam satu
faktur bulanan, dan satu pengiriman besar kadang ditagih bertahap. Bentuk 1:1
membuat kasus itu tidak bisa dicatat apa adanya.

### Keputusan: tabel penghubung

```
[NEW] purchase_invoice_receipts
  id
  purchase_invoice_id   FK purchase_invoices, cascade on delete
  goods_receipt_id      FK goods_receipts
  amount                decimal(15,2)     nilai GR ini di dalam faktur
  timestamps

  unique(purchase_invoice_id, goods_receipt_id)
  index(goods_receipt_id)
```

Tambahannya satu tabel kecil, tetapi mengubahnya nanti jauh lebih mahal:
memindahkan relasi 1:1 jadi banyak-ke-banyak setelah ada data berarti migrasi
data, dan seluruh laporan hutang harus ditulis ulang.

Nama indeks uniknya ditulis pendek (`pir_invoice_receipt_unique`) karena nama
otomatis Laravel untuk dua kolom panjang ini melewati batas 64 karakter milik
MySQL.

## Jatuh tempo

`due_date` dihitung saat faktur disimpan:

```php
$dueDate = $invoiceDate->copy()->addDays($paymentTerm?->days ?? 0);
```

`payment_term_id` disalin ke faktur, tidak dibaca dari vendor saat tampil.
Alasannya sama seperti pada PO: mengubah syarat bayar vendor tidak boleh
mengubah jatuh tempo faktur yang sudah terbit.

## Status dan perpindahannya

Status **tidak dipilih manusia**. Ia hasil perbandingan `paid_amount` dengan
`total_amount`, dihitung ulang setiap kali ada pembayaran masuk atau dibatalkan:

```php
$status = match (true) {
    $paidAmount <= 0                 => 'unpaid',
    $paidAmount < $totalAmount       => 'partial',
    default                          => 'paid',
};
```

`cancelled` hanya untuk faktur yang dibatalkan vendor, dan hanya boleh bila
belum ada pembayaran sama sekali.

`paid_amount` disimpan sebagai kolom, bukan dihitung dari pembayaran setiap
tampil, supaya daftar hutang tidak perlu menjumlah ulang ribuan baris. Sebagai
gantinya harus ada pemeriksaan di halaman Pemeriksaan Data yang membandingkan
`paid_amount` dengan jumlah `vendor_payments` — supaya kalau pernah menyimpang,
ketahuan.

## Mengisi nomor faktur ke aset

Saat faktur disimpan, nomor fakturnya diisikan ke `purchase_batches` yang lahir
dari GR terkait:

```php
PurchaseBatch::where('goods_receipt_id', $gr->id)
    ->whereNull('invoice_number')
    ->update(['invoice_number' => $invoice->invoice_number]);
```

Dengan begitu `Asset::getInvoiceNumberAttribute()` yang sudah dipakai laporan
dan ekspor terisi tanpa perubahan kode apa pun.

## Model dan relasi

```php
PurchaseInvoice   belongsTo vendor, branch, paymentTerm, createdBy
                  belongsToMany goodsReceipts (via purchase_invoice_receipts)
                  hasMany payments (VendorPayment)

GoodsReceipt      belongsToMany purchaseInvoices
```

## Laporan yang perlu menyusul

Fase 1 minimal butuh satu halaman: **Hutang Vendor**, berisi faktur belum lunas,
umur hutang, dan yang sudah lewat jatuh tempo. Bentuknya mengikuti
`BaseReportPage` yang sudah ada, sehingga dapat cetak PDF dan ekspor XLSX
otomatis.

Kolom yang berguna: vendor, nomor faktur, tanggal, jatuh tempo, umur hutang
dalam hari, total, terbayar, sisa, status.

## Pengujian yang dibutuhkan

- `due_date` dihitung benar dari payment term
- Nomor faktur boleh sama antar vendor, ditolak bila kembar di vendor yang sama
- Status berpindah `unpaid` → `partial` → `paid` mengikuti pembayaran
- Faktur yang sudah dibayar sebagian tidak bisa dibatalkan
- Nomor faktur tersalin ke `purchase_batches`, sehingga `Asset::invoice_number` terisi
