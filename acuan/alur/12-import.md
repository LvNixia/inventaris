# 12 — Import Data

**Prinsip:** import melewati Service dan validasi yang sama dengan form. Tidak ada tulis langsung ke model.

## Jenis

| Jenis | Siapa | Kunci | Perilaku |
|---|---|---|---|
| Master (karyawan, merk, divisi, cabang, kategori) | admin pusat | nama (case-insensitive, trim) | upsert; kolom kosong tidak menimpa |
| Aset baru | admin pusat; admin cabang (cabangnya) | — | kode dibuat sistem; kolom `kode_aset` diabaikan kecuali mode `--keep-codes` (hanya saat tabel kosong, untuk data awal). Nilai master tidak cocok = error baris, bukan auto-create |
| Pembaruan aset | admin pusat | `kode_aset` | hanya spesifikasi, aksesoris, vendor, invoice, harga, garansi, catatan, kondisi. **Cabang tidak bisa diubah**; kategori/jumlah/serial aset bertransaksi ditolak |
| Transaksi | admin pusat | — | urut baris; tiap baris lewat `TransactionService`/`HandoverService` (arah `out` tanpa surat hanya `type = legacy`); all-or-nothing. Untuk data awal; setelah go-live dimatikan lewat `config('inventory.allow_transaction_import')` |

Template: header persis seperti [../06-data-awal.md](../06-data-awal.md) §3 (aset) dan §4 + `pemakai` (transaksi).

## Langkah di UI (`ImportWizard`)
1. Pilih jenis → **Unduh template** (contoh 2 baris + sheet Petunjuk + sheet nilai master yang valid).
2. **Unggah** `.xlsx`/`.csv` ≤ 10 MB.
3. **Dry-run**: tabel pratinjau per baris dengan status OK / Peringatan / Error dan pesan (pesan sama dengan form: "Nomor seri wajib", "Merk 'Lenovoo' tidak ada di master", …). Ringkasan: n OK, n peringatan, n error.
4. **Proses** — aktif hanya bila 0 error (opsi "lewati baris error" untuk master & aset; **tidak** untuk transaksi).
5. Hasil: ringkasan dibuat/diperbarui/dilewati + unduh `hasil-import.xlsx` (baris asli + kolom Status & Pesan). Batch tercatat di `import_batches`.
6. **Batalkan batch** (hanya batch aset yang belum punya transaksi): soft-delete aset batch itu, status batch `reverted`.

## Proses di server
```
ImportBatch::create(status=preview)
rows = Reader::read(file)             // header dicocokkan nama, tidak peka kapital; kolom asing → peringatan
untuk tiap baris: normalisasi (tanggal: serial Excel / dd/mm/yyyy / yyyy-mm-dd; angka: "Rp 7.787.290" → 7787290;
                  spesifikasi/aksesoris: split ";") → Form Request yang sama dengan form → kumpulkan hasil
simpan pratinjau (json) ke log_path
--- Proses ---
DB::transaction (queue bila > 500 baris):
    untuk tiap baris OK: Service yang sesuai (AssetService::create / TransactionService::* / HandoverService)
    set import_batch_id pada record yang dibuat
    batch.status = done, hitung created/updated/failed
```
Import transaksi: `TransactionImporter` memproses urut baris dan menghentikan seluruh batch pada error pertama (rollback), karena baris berikutnya bergantung pada saldo hasil baris sebelumnya.

## Validasi tambahan khusus import
| Kondisi | Pesan |
|---|---|
| header wajib tidak ada | Kolom '{nama}' tidak ditemukan |
| duplikat serial di dalam file | Nomor seri muncul 2× di file (baris 7 dan 12) |
| nilai master tidak cocok | {Kolom} '{nilai}' tidak ada di master |
| `pemakai` tidak cocok karyawan | Pemakai '{nama}' tidak ada di master |
| admin cabang mengimport aset cabang lain | Cabang tidak sesuai dengan akun Anda |

**Uji:** 17.
