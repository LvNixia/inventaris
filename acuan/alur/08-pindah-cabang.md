# 08 — Pindah Cabang & Konfirmasi Terima

**Tujuan:** memindahkan aset dari cabang A ke cabang B dengan jejak dua sisi (kirim dan terima).
**Siapa:** kirim: admin pusat atau admin cabang asal; terima: admin pusat atau admin cabang tujuan.
**Prasyarat:** aset tidak sedang dipegang siapa pun (saldo pemegang = 0). Untuk barang massal, jumlah yang dikirim ≤ `qty_available`.

## Langkah di UI — kirim
1. Detail aset → **Pindah cabang** → pilih cabang tujuan, tanggal, jumlah (berseri = 1; massal boleh sebagian), nomor resi/ekspedisi (opsional), catatan.
2. **Kirim** → status aset **Dalam Perjalanan**; aset hilang dari daftar cabang asal dan muncul di daftar cabang tujuan dengan badge "menunggu konfirmasi".

## Langkah di UI — terima
1. Cabang tujuan: daftar aset → filter "Dalam Perjalanan" → **Konfirmasi terima** → kondisi saat diterima, tanggal.
2. Status berubah ke **Spare / Gudang** (bisa langsung diserahkan lewat surat).

## Langkah di UI — tolak terima
Bila kiriman salah/tidak sampai: **Tolak terima** dengan alasan → aset kembali ke cabang asal dengan status Spare / Gudang (transaksi `branch_transfer` balik). Untuk baris hasil pemisahan, baris itu **tidak** digabung kembali otomatis; admin pusat bisa menggabungkannya lewat koreksi bila perlu.

## Proses di server (`BranchTransferService`)
Kirim:
```
DB::transaction:
  asset = lockForUpdate ; validasi saldo pemegang = 0 ; quantity <= qty_available
  if quantity < asset.quantity (massal sebagian):
      asset.quantity -= quantity
      new = Asset::create(salinan atribut, quantity=quantity, asset_code=AssetCodeGenerator baru,
                          branch_id=tujuan, split_from_asset_id=asset.id)
      target = new
  else: target = asset ; target.branch_id = tujuan
  AssetTransaction::create(asset=target, type=branch_transfer, direction=neutral,
      status=Dalam Perjalanan, from_branch=A, to_branch=B, quantity, notes)
  StockCalculator::recompute(asset) ; recompute(target)
  activity log
```
Terima:
```
DB::transaction:
  target = lockForUpdate ; pastikan status Dalam Perjalanan dan branch_id = cabang user
  AssetTransaction::create(type=status_change, direction=neutral, status=Spare/Gudang, condition_after_id)
  recompute ; activity log
```

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| masih dipegang | Barang masih dipegang {nama}; tarik kembali dulu |
| jumlah > qty_available | Melebihi unit di gudang (n) |
| cabang tujuan = asal | Cabang tujuan sama dengan asal |
| konfirmasi oleh cabang lain | Hanya cabang tujuan yang bisa mengonfirmasi |

## Perubahan data
- `assets.branch_id` berubah (atau baris baru dengan `split_from_asset_id`); status Dalam Perjalanan → Spare / Gudang.
- Dua transaksi: `branch_transfer` (kirim) dan `status_change` (terima). Selama Dalam Perjalanan aset tidak bisa diserahkan (`transferable = false`).

**Uji:** 14.
