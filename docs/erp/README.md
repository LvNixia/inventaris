# Rancangan ERP Fase 1 — Pengadaan & Persediaan

Dokumen ini memecah rancangan menjadi satu berkas per submodul. Belum ada kode
yang ditulis; ini spesifikasi yang harus disetujui lebih dulu.

| Berkas | Isi |
|---|---|
| [01-persediaan.md](01-persediaan.md) | Cara stok dilacak, dan nasib barang non-serial |
| [02-pengadaan.md](02-pengadaan.md) | `payment_terms`, perubahan `vendors`, `purchase_orders` |
| [03-penerimaan.md](03-penerimaan.md) | `goods_receipts` dan mekanisme pembuatan aset |
| [04-tagihan.md](04-tagihan.md) | `purchase_invoices` |
| [05-pembayaran.md](05-pembayaran.md) | `vendor_payments` |
| [06-eksekusi.md](06-eksekusi.md) | Urutan migrasi, dampak ke kode yang ada, daftar pengujian |

## Keadaan sekarang

Aplikasi memakai model tiga lapis yang baru selesai dibangun dan diuji:

```
products          katalog barang        1 baris per jenis barang
purchase_batches  data pembelian        1 baris per baris faktur
assets            unit fisik            1 baris per unit
```

Satu baris `assets` berarti tepat satu unit fisik — berlaku untuk barang
berserial maupun tidak. Tidak ada kolom jumlah; stok dihitung dengan mencacah
baris per keadaan (`Asset::available()`, `held()`, `retired()`).

Data yang ada sekarang seluruhnya data simulasi dan boleh dibuang, sehingga
migrasi boleh diedit di tempat dan tidak perlu ada pemindahan data.

## Prinsip yang dipegang rancangan ini

**Satu barang fisik, satu baris.** Aturan ini yang menghilangkan seluruh
aritmetika stok beserta bug-nya. Rancangan ERP tidak boleh mengembalikan
representasi ganda untuk barang yang sama.

**Satu pintu masuk barang.** Setiap unit yang masuk harus lewat jalur yang
tercatat, supaya tidak ada pembelian yang lolos tanpa jejak hutang.

**Biaya menempel pada unit.** Setiap unit harus bisa menjawab berapa harganya,
kapan dibeli, dan sampai kapan garansinya — kalau tidak, laporan nilai aset
kehilangan arti.

**Tidak ada jurnal umum.** Hutang usaha dilacak lewat tabel tagihan dan
pembayaran, bukan lewat akuntansi berpasangan. Ini keputusan sadar untuk
menahan lingkup Fase 1.

## Keputusan

### K1 — Barang non-serial dilacak per unit atau per kuantitas? **SUDAH**

> **Tetap per unit.** Aksesoris perlu tercatat pemegangnya *dan* jumlahnya per
> cabang. Keduanya dilayani satu tabel: pemegang dibaca dari baris unitnya,
> jumlah dihitung dengan mencacah baris. Tidak ada tabel stok terpisah.

`stock_balances` dan `stock_movements` gugur dari Fase 1. Rinciannya di
[01-persediaan.md](01-persediaan.md).

Satu pekerjaan menyusul dari keputusan ini: perlu halaman **Stok per Cabang**
yang menyajikan hasil cacahan tersebut, karena sekarang belum ada layar yang
menampilkannya.

### K2 — Harga aset baru diambil dari mana? **SUDAH**

> **Goods Receipt menulis `purchase_batches`.** Mengikuti K1: bila GR
> menghasilkan aset, aset itu butuh harga, dan menulis ke tabel yang sudah ada
> membuat 15 berkas laporan serta ekspor tidak perlu disentuh.

`purchase_batches` berubah peran dari diisi manusia menjadi lapisan biaya yang
ditulis sistem. Rinciannya di [03-penerimaan.md](03-penerimaan.md).

### K3 — Menu Pembelian manual ditutup atau dibiarkan?

Setelah Goods Receipt ada, akan ada tiga jalur penerimaan barang: menu
Pembelian, Impor Aset, dan GR.

**Rekomendasi: menu Pembelian jadi arsip read-only**, Impor Aset tetap dibuka
untuk data lama, GR jadi satu-satunya jalur pembelian baru. Rinciannya di
[06-eksekusi.md](06-eksekusi.md).

## Alur yang dituju

```
Purchase Order          pesanan ke vendor, disetujui sebelum dikirim
      │
      ▼
Goods Receipt           barang datang; unit aset dibuat di sini
      │
      ├──────────────▶  assets + purchase_batches
      │
      ▼
Purchase Invoice        tagihan resmi vendor, jatuh tempo dari payment term
      │
      ▼
Vendor Payment          pelunasan, sebagian atau penuh
```

Serah terima, servis, mutasi cabang, dan pelepasan tidak berubah. Semuanya
tetap bekerja di atas `assets` seperti sekarang.
