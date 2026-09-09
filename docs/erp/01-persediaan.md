# 01 — Persediaan

**K1 sudah diputuskan: tetap per unit.** Aksesoris perlu tercatat pemegangnya
dan jumlahnya per cabang; keduanya dilayani satu tabel. `stock_balances` dan
`stock_movements` tidak dibangun.

Sisa dokumen ini menjelaskan alasannya, cara menghitung stoknya, dan kapan
ledger kuantitas baru masuk akal.

## Masalahnya

Rencana awal: *"Barang non-serial dikelola via Stock Ledger, barang berserial
otomatis dibuatkan Asset saat Goods Receipt."*

Di skema sekarang barang non-serial **sudah** berupa baris `assets`. Bukan
sebagian — semuanya. Pada data simulasi:

```
unit non-serial yang jadi baris assets    : 15 dari 52
unit non-serial yang pernah diserahkan    : 3
```

Ketiganya (`IDS-ACC-001`, `IDS-ACC-002`, `IDS-ACC-009`) punya kode aset,
tercatat pada surat serah terima, dan pemegangnya bernama.

Memindahkan barang non-serial ke ledger kuantitas berarti baris asetnya tidak
akan pernah dibuat lagi. Karena `handover_items.asset_id` menunjuk ke baris
aset, **modul serah terima kehilangan kemampuan menyerahkan keyboard, webcam,
dan lisensi**. Ini regresi fungsional, bukan sekadar duplikasi data.

Sekaligus memunculkan kembali persoalan yang baru dihilangkan: satu barang
terwakili di dua tempat, dengan kebenaran yang bisa berbeda.

## Keputusan: tidak ada tabel stok baru

`assets` tetap satu-satunya daftar unit untuk semua barang tahan lama, berserial
maupun tidak. Stok dihitung dari sana — mekanismenya sudah ada dan sudah diuji:

```php
Asset::query()->available()->count()   // di gudang, siap diserahkan
Asset::query()->held()->count()        // sedang dipegang karyawan
Asset::query()->retired()->count()     // sudah dihapusbukukan
Asset::query()->active()->count()      // masih dimiliki perusahaan
```

Saldo per cabang per barang:

```php
Asset::query()
    ->available()
    ->where('branch_id', $branchId)
    ->where('product_id', $productId)
    ->count();
```

Riwayat pergerakan sudah ada di `asset_transactions`, lengkap dengan
`stock_direction` (`in`, `out`, `neutral`, `writeoff`), tanggal, pelaku, dan
dokumen pemicunya.

### Halaman Stok per Cabang — sudah dibangun

Kebutuhan "perlu tahu jumlahnya di cabang" dijawab
[`LaporanStokCabang`](../../app/Filament/Pages/Reports/LaporanStokCabang.php),
satu baris per kombinasi cabang dan barang:

| Cabang | Barang | Total | Tersedia | Dipegang | Servis/Rusak | Dilepas |
|---|---|---|---|---|---|---|
| Jakarta | Logitech MK270 | 12 | 11 | 1 | 0 | 0 |
| Jakarta | Logitech C920 | 2 | 2 | 0 | 0 | 0 |
| Batam | Logitech C920 | 1 | 1 | 0 | 0 | 0 |

Angkanya dicacah langsung dari baris aset dengan `SUM(CASE WHEN ...)` dalam satu
kueri, bukan dibaca dari tabel saldo. Karena itu jumlah di sini **tidak mungkin
berbeda** dari daftar unitnya — keduanya sumber yang sama. Diverifikasi pada
data simulasi: jumlah seluruh baris rekap sama dengan 52 unit yang tercatat.

Halaman ini memakai `BaseReportPage`, jadi otomatis punya cetak PDF, ekspor
XLSX, dan penyaring cabang serta kategori. Pemegang tiap unit dilihat lewat
laporan Kepemilikan Aset yang sudah ada.

### Yang gugur dari Fase 1

| Tabel di rencana awal | Alasan gugur |
|---|---|
| `stock_balances` | Saldo dihitung dari `assets`, tidak perlu disimpan dan disinkronkan |
| `stock_movements` | Riwayat sudah ada di `asset_transactions` |

Membuat keduanya berarti mengelola dua ledger untuk kejadian yang sama.
`movement_type` pada rencana awal bahkan memuat `transfer_out` dan
`transfer_in`, padahal mutasi cabang sudah dicatat `asset_transactions` —
keduanya akan menyimpang cepat.

## Bila tetap ingin ledger kuantitas

Ada satu kasus yang memang tidak cocok dilacak per unit: **barang habis pakai**
— tinta, baterai, kabel curah, alat tulis. Barang seperti ini tidak diserahkan
ke orang tertentu dan tidak punya riwayat individual.

Itu konsep berbeda dari "non-serial", dan sebaiknya ditandai eksplisit:

```
[MODIFY] products
  + is_consumable  boolean, default false
```

Barang habis pakai tidak membuat baris `assets` sama sekali; ia hanya menambah
saldo. Barang tahan lama tetap per unit, apa pun status serialnya.

Kalau jalur ini diambil, tabelnya:

```
[NEW] consumable_balances
  branch_id     FK branches
  product_id    FK products
  quantity      integer
  unique(branch_id, product_id)

[NEW] consumable_movements
  branch_id     FK branches
  product_id    FK products
  quantity      integer            positif masuk, negatif keluar
  movement_type enum: goods_receipt, issue, adjustment, transfer_in, transfer_out
  reference_type, reference_id     polymorphic ke dokumen pemicu
  notes         text nullable
  created_by    FK users
  timestamps
```

Dua catatan teknis bila ini dibangun:

- `consumable_balances` wajib `unique(branch_id, product_id)`, dan mutasinya
  harus memakai `lockForUpdate()` atau `upsert` — tanpa itu dua penerimaan
  bersamaan bisa saling menimpa saldo.
- Saldo sebaiknya bisa dihitung ulang dari `consumable_movements` kapan saja,
  supaya ada cara memperbaiki bila pernah menyimpang. Halaman Pemeriksaan Data
  perlu pemeriksaan baru untuk itu.

**Saran saya: tunda ke fase berikutnya.** Fase 1 tidak butuh barang habis pakai,
dan menambahkannya sekarang berarti dua model persediaan berjalan sekaligus
sebelum alur pengadaannya sendiri terbukti jalan.

## Dampak ke kode yang ada

Tidak ada tabel baru, tidak ada model baru, tidak ada berkas lama yang berubah.
Yang bertambah hanya satu halaman laporan.

Sebagai pembanding, bila jalur ledger yang diambil: seluruh laporan yang
mencacah `assets` harus menggabungkan dua sumber, `AssetSelect` pada form serah
terima harus memutuskan barang mana yang boleh muncul, dan setiap mutasi harus
menulis ke dua tempat sekaligus.

## Pengujian yang dibutuhkan

Cakupan yang sudah ada tetap berlaku:

- `PerUnitAssetTest::test_pembelian_membuat_satu_baris_aset_per_unit`
- `PerUnitAssetTest::test_servis_satu_unit_tidak_mengunci_unit_sejenis`
- `StockCalculatorTest::test_penghitung_membedakan_unit_tersedia_dipegang_dan_dilepas`
- `PanelAccessTest` membuka halaman Stok per Cabang
