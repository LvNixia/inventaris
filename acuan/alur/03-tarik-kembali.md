# 03 — Tarik Kembali (Pengembalian ke Gudang)

**Tujuan:** mencatat barang yang dikembalikan pemegangnya ke IT/gudang, sekaligus kondisi saat diterima.
**Siapa:** admin pusat; admin cabang (cabangnya).
**Prasyarat:** aset punya saldo pemegang > 0.

## Langkah di UI
1. Dari detail aset → **Tarik kembali**, atau Transaksi → **Buat** → status **Spare / Gudang** → pilih aset (dropdown hanya aset yang sedang dipegang, label menyertakan nama pemegang).
2. Isi: tanggal; **Dari** (pemegang — otomatis bila aset berseri; untuk barang massal pilih dari daftar pemegang dengan saldonya); **Ke** (petugas yang menerima, default: karyawan tertaut akun); jumlah (default = saldo pemegang untuk berseri = 1); **Kondisi saat diterima**; aksesoris yang ikut kembali (checklist dari `accessories`); catatan.
3. **Simpan** → riwayat bertambah; tombol **Cetak bukti pengembalian**.

## Proses di server (`TransactionService::return`)
```
DB::transaction:
  asset = lockForUpdate
  saldo = StockCalculator::holderBalance(asset, from_employee_id)
  validasi quantity <= saldo
  AssetTransaction::create(type=return, direction=in, status=Spare/Gudang,
      from=pemegang, to=petugas, quantity, condition_after_id, notes)
  if condition_after_id: asset.condition_id = condition_after_id
  StockCalculator::recompute(asset)   → holder null (clears_holder), status Spare/Gudang
  activity log
```

## Validasi & pesan (§5)
| Kondisi | Pesan |
|---|---|
| `from` kosong | Pilih siapa yang mengembalikan |
| jumlah > saldo pemegang | Kembali melebihi yang dipegang (dipegang: n) |
| aset tidak pernah keluar | Barang ini belum pernah diserahkan |
| jumlah ≤ 0 | Jumlah harus lebih dari 0 |

## Perubahan data
- `asset_transactions`: `return`.
- `assets`: `qty_in` +n, `qty_available` +n, `current_holder`/`current_user` null (berseri) atau tetap penerima terakhir (massal, saldo per pemegang yang berubah), `current_status = Spare / Gudang`, `condition_id` diperbarui.
- Aset muncul lagi di dropdown surat.

## Bukti pengembalian
PDF satu halaman ([../05-surat-pdf.md](../05-surat-pdf.md) §2) dari transaksi yang baru dibuat; tidak memakai counter nomor surat.

**Uji:** 2, 5, 6, 16.
