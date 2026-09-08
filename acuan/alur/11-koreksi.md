# 11 — Koreksi Salah Catat

**Tujuan:** memperbaiki riwayat tanpa menghapus atau mengedit transaksi lama.
**Siapa:** admin pusat.
**Prinsip:** transaksi lama tetap ada; koreksi adalah transaksi baru dengan `type = correction` yang merujuk ke transaksi yang dikoreksi (`notes` wajib menyebut #id dan alasan). Bila kebijakan "hapus < 24 jam untuk transaksi terakhir" diaktifkan (keputusan terbuka #8), itu jalur terpisah dengan alasan wajib dan audit.

## Kasus & cara koreksinya

| Salah catat | Koreksi |
|---|---|
| Jumlah pengembalian terlalu besar (mis. 6 padahal 3) | `correction` arah `out` qty 3, `to` = pemegang semula, tanpa surat (`handover_document_id` null diizinkan hanya untuk `type = correction`) |
| Jumlah pengembalian terlalu kecil | `correction` arah `in` qty selisih |
| Salah pemegang pada pengembalian (dari A padahal dari B) | dua `correction`: `out` ke A qty n (mengembalikan saldo A) dan `in` dari B qty n |
| Status salah (Rusak padahal Servis) | `status_change` biasa ke status yang benar; tidak perlu `correction` |
| Salah kondisi | edit `assets.condition_id` langsung + `status_change` dengan `condition_after_id` sebagai jejak |
| Surat salah orang/barang, belum ada transaksi lanjutan | [07-batalkan-surat.md](07-batalkan-surat.md) |
| Surat salah, sudah ada transaksi lanjutan | `correction` arah `in` untuk item yang salah (mengembalikan ke gudang), lalu surat baru bila perlu; surat lama diberi catatan (kolom `notes` surat) tapi tetap `issued` |
| Dilepas padahal tidak | `correction` dengan `direction = writeoff` dan `quantity` **negatif** (−n), status Spare / Gudang. Arah `in` tidak bisa dipakai karena `qty_writeoff` hanya dihitung dari arah `writeoff`. Kuantitas negatif hanya diizinkan untuk `type = correction` arah `writeoff`; validasi memastikan `qty_writeoff` hasil akhir ≥ 0 |

## Langkah di UI
Transaksi → **Koreksi** → pilih transaksi yang dikoreksi (pencarian #id / aset) → sistem menawarkan pola koreksi sesuai tabel di atas dan mengisi arah/jumlah default → alasan wajib → **Simpan**.

## Tanggal koreksi
`transaction_date` koreksi = hari ini (bukan tanggal kejadian yang dikoreksi), supaya koreksi menjadi transaksi terakhir dan `current_*` dihitung darinya. Tanggal kejadian sebenarnya ditulis di `notes`.

## Proses di server (`TransactionService::correct`)
Sama seperti pola umum; validasi stok tetap berlaku (hasil akhir tidak boleh membuat `qty_available < 0` atau saldo pemegang negatif). `notes` = "Koreksi #{id}: {alasan}".

## Perubahan data
- Transaksi `correction`; cache aset dihitung ulang.
- Halaman Pemeriksaan Data harus kembali nol setelah koreksi.
