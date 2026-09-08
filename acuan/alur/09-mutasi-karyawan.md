# 09 — Mutasi Karyawan Antar Cabang

**Tujuan:** memindahkan karyawan ke cabang lain **bersama aset yang dipegangnya**, tanpa harus tarik-serahkan ulang.
**Siapa:** admin pusat.
**Prasyarat:** karyawan aktif; cabang tujuan aktif.

## Langkah di UI
1. Karyawan → detail → **Mutasi** → pilih cabang tujuan, tanggal efektif, divisi baru (opsional), jabatan baru (opsional).
2. Sistem menampilkan daftar aset yang dipegang (dengan saldo untuk barang massal) dan menawarkan dua pilihan per aset: **ikut pindah** (default) atau **dikembalikan dulu** (membuka [03](03-tarik-kembali.md)).
3. **Proses**.

## Proses di server (`BranchTransferService::transferEmployee`)
```
DB::transaction:
  employee = lockForUpdate ; employee.branch_id = tujuan (+ division/position bila diisi)
  untuk tiap aset "ikut pindah":
      asset = lockForUpdate ; n = holderBalance(asset, employee)
      if asset.quantity == 1 (atau seluruh unit aset dipegang employee dan tidak ada unit lain):
          asset.branch_id = tujuan
          AssetTransaction::create(type=branch_transfer, direction=neutral, status=Dipakai,
              from_branch, to_branch, to_employee=employee, quantity=n, notes="Mutasi karyawan")
          recompute(asset)     // holder tetap employee; status Dipakai; tidak Dalam Perjalanan
      else (barang massal, sebagian dipegang employee):
          // unit yang dipegang harus dipindahkan BESERTA riwayat "keluar"-nya, bukan sekadar dipisah
          asset.quantity -= n
          AssetTransaction::create(asset, type=branch_transfer, direction=in, status=Spare/Gudang,
              from=employee, quantity=n, notes="Mutasi: unit ikut ke {tujuan}")
          new = Asset::create(salinan atribut, quantity=n, asset_code baru, branch_id=tujuan, split_from_asset_id=asset.id)
          AssetTransaction::create(new, type=branch_transfer, direction=out, status=Dipakai,
              to=employee, user=..., quantity=n, handover_document_id=null, notes="Mutasi: dipegang sejak {tanggal asal}")
          recompute(asset) ; recompute(new)
          // hasil: aset asal — available tetap, saldo employee 0; aset baru — available 0, saldo employee n
  activity log
```
Tidak ada langkah konfirmasi cabang tujuan, karena barang ikut orangnya; cabang tujuan cukup melihatnya di daftar.

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| tujuan = cabang sekarang | Karyawan sudah di cabang itu |
| akun user karyawan ini punya `branch_id` lama | otomatis ikut diperbarui; ditampilkan sebagai info |

## Perubahan data
- `employees.branch_id` (+ `users.branch_id` bila ada akun tertaut).
- `assets.branch_id` untuk aset yang ikut; transaksi `branch_transfer` per aset dengan pemegang tetap.

**Uji:** turunan 14 (tambahkan kasus: mutasi karyawan dengan 2 aset → keduanya berpindah cabang, pemegang tetap, tidak ada status Dalam Perjalanan).
