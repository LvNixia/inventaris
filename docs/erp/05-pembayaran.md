# 05 — Pembayaran Vendor

Pencatatan pelunasan tagihan.

> **Status: sudah dibangun.** Model,
> [`VendorPaymentService`](../../app/Services/VendorPaymentService.php), dan
> Filament Resource.
>
> **K5 diputuskan: satu pembayaran boleh melunasi beberapa faktur.** Tabel
> alokasi `vendor_payment_allocations` dibangun.

## `vendor_payments` (baru)

```
id
payment_number     string, unique              dibuat sistem
vendor_id          FK vendors
branch_id          FK branches
payment_date       date
amount             decimal(15,2)               total yang dibayarkan
payment_method     enum: transfer, cash, giro, lainnya
reference_number   string nullable             nomor bukti transfer
notes              text nullable
created_by         FK users nullable
timestamps

index(vendor_id, payment_date)
index(payment_date)
```

## Satu pembayaran melunasi berapa faktur?

Rencana awal memakai `purchase_invoice_id` tunggal — satu pembayaran hanya bisa
melunasi satu faktur.

Kenyataannya satu transfer sering melunasi beberapa faktur sekaligus, terutama
untuk vendor langganan yang ditagih mingguan lalu dibayar bulanan. Bentuk 1:1
memaksa satu transfer dipecah jadi beberapa catatan pembayaran yang nomor
buktinya sama, dan rekonsiliasi dengan rekening koran jadi sulit.

### Keputusan: tabel alokasi

```
[NEW] vendor_payment_allocations
  id
  vendor_payment_id     FK vendor_payments, cascade on delete
  purchase_invoice_id   FK purchase_invoices
  amount                decimal(15,2)     porsi pembayaran untuk faktur ini
  timestamps

  unique(vendor_payment_id, purchase_invoice_id)
  index(purchase_invoice_id)
```

Satu transfer Rp 50 juta bisa dialokasikan Rp 30 juta ke faktur A dan Rp 20 juta
ke faktur B, dengan satu nomor bukti transfer.

Perbandingan nilai memakai toleransi setengah rupiah, supaya pembulatan desimal
tidak menolak pembayaran yang sebenarnya sudah pas.

## Aturan saat pembayaran disimpan

Seluruhnya dalam satu transaksi basis data.

```
1. Jumlah alokasi harus sama persis dengan amount
2. Tiap alokasi tidak boleh melebihi sisa faktur
   sisa = total_amount - paid_amount
3. Faktur berstatus cancelled tidak bisa dibayar
4. Tambahkan alokasi ke paid_amount tiap faktur
5. Hitung ulang status tiap faktur: unpaid / partial / paid
6. Terbitkan payment_number
```

Aturan nomor 2 yang mencegah kelebihan bayar. Bila vendor benar-benar dibayar
lebih, itu harus dicatat sebagai uang muka atau kelebihan bayar — konsep
tersendiri yang **tidak masuk Fase 1**.

## Pembatalan pembayaran

Membatalkan pembayaran mengurangi kembali `paid_amount` tiap faktur yang
dialokasikan, lalu menghitung ulang statusnya. Karena tidak ada jurnal umum,
pembatalan cukup menghapus barisnya — tapi lebih baik ditandai batal daripada
dihapus, supaya nomor pembayaran tidak pernah dipakai ulang.

```
+ cancelled_at    timestamp nullable
+ cancel_reason   text nullable
```

Pembayaran yang sudah dibatalkan tidak ikut dihitung.

## Penomoran

Memakai `DocumentNumberGenerator` yang sudah ada, berjalan per cabang per bulan,
dengan awalan berbeda dari surat serah terima. Contoh: `PAY/JKT/2026/09/001`.

## Model dan relasi

```php
VendorPayment   belongsTo vendor, branch, createdBy
                hasMany allocations (VendorPaymentAllocation)
                belongsToMany purchaseInvoices (via allocations)

PurchaseInvoice hasMany allocations
                belongsToMany payments
```

## Pemeriksaan Data

Dua pemeriksaan baru untuk halaman Pemeriksaan Data yang sudah ada:

- `paid_amount` pada faktur tidak sama dengan jumlah alokasi pembayarannya
- faktur berstatus `paid` tetapi `paid_amount` kurang dari `total_amount`

Keduanya menangkap penyimpangan yang bisa muncul bila ada penulisan langsung ke
basis data di luar alur aplikasi.

## Pengujian — sudah ada

[`tests/Unit/PayableTest.php`](../../tests/Unit/PayableTest.php) memuat 18
pengujian untuk faktur dan pembayaran sekaligus. Yang dijaga:

- Jumlah alokasi tidak sama dengan `amount` → ditolak
- Alokasi melebihi sisa faktur → ditolak
- Pembayaran penuh mengubah status faktur jadi `paid`
- Pembayaran sebagian mengubah status jadi `partial`
- Satu pembayaran melunasi dua faktur sekaligus
- Faktur `cancelled` tidak bisa dibayar
- Pembatalan pembayaran mengembalikan `paid_amount` dan status faktur
