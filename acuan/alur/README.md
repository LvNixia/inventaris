# Alur per Aksi

Satu file per aksi. Kerangka tiap file sama: **Tujuan · Siapa · Prasyarat · Langkah di UI · Proses di server · Validasi & pesan · Perubahan data · Uji terkait**. Nomor aturan (§) merujuk ke [../03-aturan-bisnis.md](../03-aturan-bisnis.md).

| # | Aksi | Service | Transaksi yang dibuat |
|---|---|---|---|
| [01](01-tambah-aset.md) | Tambah / edit aset | `AssetCodeGenerator` | — |
| [02](02-terbitkan-surat.md) | Buat draft & terbitkan surat serah terima | `HandoverService` | `handover` (out) per item |
| [03](03-tarik-kembali.md) | Tarik kembali (pengembalian ke gudang) | `TransactionService` | `return` (in) |
| [04](04-pindahkan-ke-orang-lain.md) | Pindahkan ke orang lain | `HandoverService` (mode langsung) | `return` (in) + `handover` (out) saat surat terbit |
| [05](05-ubah-status.md) | Ubah status (Servis / Rusak / Baru Didaftarkan) | `TransactionService` | `status_change` (neutral) |
| [06](06-dilepas.md) | Dilepas / dijual | `TransactionService` | `disposal` (writeoff) |
| [07](07-batalkan-surat.md) | Batalkan surat | `HandoverCancellation` | `cancellation` (in) per item |
| [08](08-pindah-cabang.md) | Pindah cabang & konfirmasi terima | `BranchTransferService` | `branch_transfer` (neutral), lalu `status_change` |
| [09](09-mutasi-karyawan.md) | Mutasi karyawan antar cabang | `BranchTransferService` | `branch_transfer` per aset |
| [10](10-nonaktifkan-karyawan.md) | Nonaktifkan karyawan | `EmployeeOffboarding` | `return` per aset |
| [11](11-koreksi.md) | Koreksi salah catat | `TransactionService` | `correction` (arah sesuai kebutuhan) |
| [12](12-import.md) | Import (master, aset, pembaruan, transaksi) | `Services/Import/*` | sesuai jenis |
| [13](13-export.md) | Export & backup workbook | `Services/Export/*` | — |
| [14](14-servis-upgrade.md) | Servis, perbaikan & upgrade (buka → selesai; perubahan spesifikasi) | `ServiceService` | `status_change` (Servis / kembali) atau `return`, dengan `service_id` |
| [15](15-kelola-master.md) | Kelola master data (tambah/ubah/nonaktif/hapus/gabung) | `MasterService` + `MasterRegistry` | — |

Pola umum proses di server (semua aksi yang menulis transaksi):

```
DB::transaction(function () {
    $asset = Asset::lockForUpdate()->find($id);      // 1. kunci baris aset
    $this->validate($asset, $input);                 // 2. validasi ulang dengan data terkunci
    $trx = AssetTransaction::create([...]);          // 3. tulis ledger (stock_direction di-snapshot)
    if ($input->condition_after_id) $asset->condition_id = ...;
    $this->stock->recompute($asset);                 // 4. hitung ulang cache qty_* & current_*
    activity()->performedOn($asset)->log(...);       // 5. audit
});
```
Validasi di langkah 2 mengulang validasi Form Request, karena antara form ditampilkan dan disubmit, stok bisa berubah oleh pengguna lain.
