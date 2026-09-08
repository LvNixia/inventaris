# 10 — Nonaktifkan Karyawan (Resign / Keluar)

**Tujuan:** memastikan semua aset ditarik sebelum karyawan dinonaktifkan.
**Siapa:** admin pusat; admin cabang (karyawan cabangnya).

## Langkah di UI
1. Karyawan → detail → **Nonaktifkan**.
2. Bila masih memegang aset: ditolak; tampil daftar aset (kode, nama, saldo) dan tombol **Tarik semua**.
3. **Tarik semua** → form ringkas: tanggal, petugas penerima (default: akun login), kondisi saat diterima per aset (default: kondisi saat ini), catatan → **Proses** → satu bukti pengembalian untuk semua aset.
4. Ulangi **Nonaktifkan** → berhasil; akun user tertaut (bila ada) ikut dinonaktifkan.

## Proses di server (`EmployeeOffboarding`)
```
DB::transaction:
  held = StockCalculator::assetsHeldBy(employee)   // saldo > 0, termasuk yang sedang Servis/Rusak atas namanya
  untuk tiap: TransactionService::return(asset, from=employee, to=petugas, qty=saldo, condition_after)
  activity log
```
Nonaktifkan:
```
if StockCalculator::assetsHeldBy(employee) tidak kosong → tolak
employee.is_active = false ; user?.is_active = false
```

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| masih memegang aset | Karyawan masih memegang n aset; tarik semua dulu |
| karyawan adalah `first_party` di draft surat | Ada draft surat atas nama karyawan ini; hapus atau ganti dulu |

## Perubahan data
- Transaksi `return` per aset; aset kembali ke gudang.
- `employees.is_active = false`; `users.is_active = false`.
- Karyawan nonaktif tidak muncul di dropdown penerima, tetapi tetap tampil di riwayat lama.

**Uji:** 15.
