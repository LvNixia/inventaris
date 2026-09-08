# 03 — Aturan Bisnis

Aturan di sini diimplementasikan di `app/Services` dan Form Request, bukan di view. Tiap alur di [alur/](alur/README.md) merujuk ke nomor bagian di sini.

## 0. Rujukan ke master
Kode aplikasi merujuk entri master bersistem lewat `code` (`AssetStatus::byCode('spare')`, `Condition::byCode('hilang')`, `DisposalReason::where('is_lost', true)`), tidak pernah lewat nama — nama bebas diubah pengguna ([11-master-data.md](11-master-data.md)). Dropdown hanya menampilkan entri `is_active`; relasi lama tetap memuat entri nonaktif.

## 1. Kode aset
```
IDS-{category.code_prefix}-{seq:3 digit}
```
- `seq` = `categories.last_seq + 1`, diambil dalam DB transaction dengan `lockForUpdate()` pada baris kategori.
- Tidak pernah berubah. Kategori aset yang sudah punya transaksi tidak bisa diganti; yang belum: hapus & buat ulang.
- Prefix wajib saat membuat kategori dan **tidak bisa diubah** setelah kategori punya aset (kode lama tidak diganti; prefix baru akan membuat dua pola kode dalam satu kategori).

## 2. Stok
```
qty_out       = Σ quantity transaksi arah out
qty_in        = Σ quantity transaksi arah in
qty_writeoff  = Σ quantity transaksi arah writeoff
qty_available = quantity − qty_out + MIN(qty_in, qty_out) − qty_writeoff
saldo pemegang X (barang massal) = Σ out ke X − Σ in dari X
```
`MIN` hanya pengaman untuk data awal; pada data baru `qty_in > qty_out` dicegah validasi §5.

## 3. Pemegang & status saat ini
Dari transaksi terakhir aset (`ORDER BY transaction_date DESC, id DESC`):
- `current_status` = status transaksi terakhir; tanpa transaksi → "Belum diserahkan".
- `current_holder` / `current_user`:
  - status ber-`clears_holder` (Spare / Gudang, Dilepas / Dijual, Dalam Perjalanan) → null;
  - arah `out` → `to_employee_id` / `user_employee_id`;
  - status lain (Servis, Rusak, Baru Didaftarkan) → `to_employee_id` bila diisi, kalau kosong pertahankan pemegang sebelumnya. Laptop yang diservis tetap atas nama orangnya.
- Barang massal (`quantity > 1`): `current_holder` hanya "penerima terakhir" dan `current_status` hanya status transaksi terakhir — keduanya menyesatkan untuk barang massal. UI menampilkan ringkasan terhitung: **Tersedia n · Dipegang m (daftar) · Dilepas k**, dan filter status untuk barang massal memakai ringkasan itu, bukan `current_status`.
- Perubahan **spesifikasi** aset hanya lewat dua jalur: edit langsung (koreksi salah ketik, tercatat di activity log) atau **selesai upgrade** ([alur/14](alur/14-servis-upgrade.md)) yang menyimpan `spec_before`/`spec_after`. Status Servis tidak mengubah pemegang, stok, maupun harga aset; biaya servis dicatat terpisah dan tidak dikapitalisasi.
- Transaksi `status_change` dengan status yang sama seperti status saat ini ditolak, **kecuali** `service_id` terisi (jejak upgrade/perawatan tanpa perubahan status).
- `transaction_date` untuk transaksi yang dibuat sistem (pembatalan, koreksi, konfirmasi terima) = hari ini, supaya selalu menjadi transaksi terakhir. Transaksi manual dengan tanggal lebih awal dari transaksi terakhir aset diberi peringatan (keputusan terbuka #12).

`StockCalculator::recompute(Asset)` menghitung semua nilai di atas dan menulisnya ke kolom cache. Dipanggil oleh setiap Service setelah menulis transaksi, masih di dalam DB transaction yang sama.

## 3a. Cabang aset mengikuti tempatnya berada
Satu aturan untuk semua kasus lintas cabang: **`assets.branch_id` = cabang orang yang memegangnya, atau cabang gudang yang menyimpannya.** Konsekuensinya:
- Surat serah terima kepada karyawan cabang lain (hanya admin pusat yang bisa) → sistem otomatis menambah transaksi `branch_transfer` bersama transaksi `handover`, `branch_id` aset berpindah ke cabang penerima, tanpa status Dalam Perjalanan.
- Pengembalian ke petugas cabang lain → `branch_id` mengikuti petugas.
- Mutasi karyawan → aset yang dipegangnya ikut ([alur/09](alur/09-mutasi-karyawan.md)).
- Pengiriman fisik antar gudang → [alur/08](alur/08-pindah-cabang.md) dengan Dalam Perjalanan dan konfirmasi.

## 3b. BranchScope di luar request HTTP
`BranchScope` membaca user yang login. Di CLI, job queue, dan scheduler tidak ada user: scope harus **tidak memfilter apa pun** (bukan mengembalikan kosong), dan job yang memang per cabang (export admin cabang) menerima `branch_id` eksplisit sebagai parameter. Tanpa ini `stock:recompute` dan export ter-queue akan bekerja pada set data yang salah atau kosong.

## 4. Aset yang boleh dipilih
- **Untuk surat**: `qty_available > 0` **dan** (`current_status.transferable = true` atau belum berstatus) **dan** `branch_id` = cabang surat. Admin pusat boleh memilih aset cabang mana pun; cabang aset lalu mengikuti penerima (§3a).
- **Untuk pengembalian**: aset dengan saldo pemegang > 0; dropdown menampilkan pemegangnya.
- **Untuk dilepas**: `qty_available > 0`.

Implementasi: scope Eloquent `Asset::availableForHandover($branchId)`, `Asset::heldBy($employeeId)`, `Asset::disposable()`.

## 5. Validasi transaksi

| Aturan | Pesan | Implementasi |
|---|---|---|
| Status `requires_qty` → `quantity` > 0 | "Jumlah harus lebih dari 0" | Form Request |
| Arah `out`: `quantity ≤ qty_available` | "Melebihi sisa tersedia" | Form Request + cek ulang dalam DB transaction dengan lock baris aset |
| Arah `out` wajib `to_employee_id`; `handover_document_id` wajib untuk `type = handover` (null diizinkan untuk `legacy`, `correction`, `branch_transfer`) | — | Service |
| Arah `in`: `from_employee_id` wajib, `quantity ≤` saldo pemegang itu | "Kembali melebihi yang dipegang" | Form Request |
| Arah `writeoff`: `quantity ≤ qty_available` | "Unit masih dipegang, tarik dulu" | Form Request |
| Status `transferable = false` tidak bisa diserahkan | — | Form Request |
| Satu aset sekali per surat | "Barang ini dipilih lebih dari sekali" | unique index |
| `transaction_date` tidak lebih awal dari transaksi terakhir aset itu | "Tanggal lebih awal dari riwayat terakhir" (peringatan, bukan penolakan; admin pusat boleh lanjut) | Form Request |

## 6. Validasi aset

| Aturan | Pesan |
|---|---|
| `category.requires_serial` dan serial kosong | "Nomor seri wajib" |
| serial duplikat | "Nomor seri duplikat" |
| `quantity ≥ 1` | "Jumlah kosong" |
| `purchase_date ≤ today` | "Tanggal di masa depan" |
| `warranty_until ≥ purchase_date` | "Garansi lebih awal dari tanggal beli" |
| `condition.applies_to` cocok dengan kategori (Lisensi → license, lainnya → hardware) | "Kondisi tidak berlaku untuk kategori ini" |

Halaman **Pemeriksaan Data** (`DataCheckService`) menampilkan aset/transaksi yang melanggar aturan di atas atau punya `qty_available < 0` / `qty_in > qty_out`. Seharusnya nol pada data baru.

## 7. Nomor surat
```
IDS-IT/{branch.code}/{YYYY}/{MM}/{NN}
```
`NN` urut per cabang per bulan, minimal 2 digit, reset tiap bulan. Dibuat saat **terbit** dengan `lockForUpdate()` pada `document_counters`. Nomor yang dibatalkan tidak dipakai ulang.

## 8. Draft
Draft tidak mengunci stok. Dua draft boleh memuat aset yang sama; yang terbit lebih dulu menang. Draft tak tersentuh > 30 hari ditandai kedaluwarsa oleh `drafts:expire`.

## 9. Pembatalan surat
Hanya bila tidak ada transaksi lain untuk aset-aset di surat itu setelah transaksi yang dibuat surat tersebut. Detail: [alur/07-batalkan-surat.md](alur/07-batalkan-surat.md).

## 10. Pindah cabang
Aset tidak sedang dipegang; barang massal sebagian dipisah jadi baris aset baru; status Dalam Perjalanan sampai cabang tujuan konfirmasi. Detail: [alur/08-pindah-cabang.md](alur/08-pindah-cabang.md).

## 11. Karyawan nonaktif
Tidak bisa dinonaktifkan selama saldo pemegangnya > 0. Detail: [alur/10-nonaktifkan-karyawan.md](alur/10-nonaktifkan-karyawan.md).
