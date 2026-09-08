# 05 — Ubah Status (Servis / Rusak / Baru Didaftarkan)

**Tujuan:** mengubah status aset tanpa memindahkan unit (stok tidak berubah). Untuk servis/perbaikan/upgrade yang butuh detail (vendor, biaya, hasil, perubahan spesifikasi), gunakan [14-servis-upgrade.md](14-servis-upgrade.md) — alur ini hanya penanda status cepat.
**Siapa:** admin pusat; admin cabang (cabangnya).

## Langkah di UI
1. Detail aset → **Ubah status** → pilih Servis / Rusak / Baru Didaftarkan; untuk aset yang **sedang dipegang**, pilihan tambahan **Dipakai** (selesai servis, pemegang sama); untuk aset yang **di gudang**, pilihan tambahan **Spare / Gudang** (selesai servis di gudang, agar bisa diserahkan lagi) — keduanya dicatat `status_change` arah neutral tanpa jumlah.
2. Isi tanggal, kondisi baru (opsional), catatan (mis. nomor servis, vendor servis). Jumlah tidak diminta (status `requires_qty = false`).
3. **Simpan**.

## Proses di server (`TransactionService::changeStatus`)
```
DB::transaction:
  asset = lockForUpdate
  AssetTransaction::create(type=status_change, direction=neutral, status=X,
      from=null, to=asset.current_holder_id (dipertahankan), quantity=null, condition_after_id, notes)
  if condition_after_id: asset.condition_id = ...
  StockCalculator::recompute(asset)   → qty tidak berubah; holder tetap (clears_holder=false); status X
  activity log
```

Catatan "selesai servis": status Servis → Dipakai untuk pemegang yang sama **bukan** transaksi Keluar baru (unit tidak berpindah), jadi dicatat sebagai `status_change` dengan status Dipakai, `direction = neutral` (di-snapshot dari konteks, bukan dari master status). Ini satu-satunya kasus status Dipakai dengan arah neutral; `TransactionService` yang menentukannya, bukan pengguna. Kalau barang selesai servis dan diserahkan ke orang **lain**, gunakan [03](03-tarik-kembali.md) lalu [02](02-terbitkan-surat.md).

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| status sama dengan status saat ini (tanpa `service_id`) | Status tidak berubah |
| Dipakai dipilih padahal aset tidak sedang dipegang | Barang tidak sedang dipegang siapa pun; gunakan surat serah terima |

## Perubahan data
- `asset_transactions`: `status_change`.
- `assets`: `current_status` berubah; `qty_*` tetap; `current_holder` tetap; `condition_id` bila diisi.
- Servis/Rusak: aset hilang dari dropdown surat (`transferable = false`) walau `qty_available > 0`.

**Uji:** 7, 20.
