# 01 — Tambah / Edit Aset

**Tujuan:** mendaftarkan barang ke register dengan kode aset yang dibuat sistem.
**Siapa:** admin pusat; admin cabang (untuk cabangnya).
**Prasyarat:** kategori, merk, kondisi, cabang sudah ada di master.

## Langkah di UI
1. Register Aset → **Tambah**.
2. Pilih **Kategori** dulu. Setelah dipilih: field Nomor Seri menjadi wajib/opsional sesuai `requires_serial`; dropdown Kondisi difilter `applies_to`; pratinjau "Kode akan menjadi IDS-LAP-006".
3. Isi merk, tipe/model, jumlah (default 1; barang massal isi jumlah unit), serial/IMEI, spesifikasi & aksesoris (input daftar, satu baris per item), kondisi, data pembelian (vendor dari master Vendor dengan "+ Tambah baru", faktur, harga), garansi, cabang (terkunci untuk admin cabang), catatan.
4. **Simpan** → detail aset. Unggah lampiran (foto, faktur, kartu garansi) dari tab Lampiran bila ada.

Edit: form sama. Field yang **terkunci** bila aset sudah punya transaksi: kategori, jumlah, nomor seri, cabang. Kode aset selalu terkunci.

## Proses di server (`AssetCodeGenerator` + `AssetService::create`)
```
DB::transaction:
  category = Category::lockForUpdate()->find(category_id)
  seq = category.last_seq + 1 ; category.last_seq = seq
  asset_code = "IDS-{prefix}-{seq:03d}"
  Asset::create([...]) ; qty_available = quantity ; current_status_id = null
  activity log
```
Pratinjau kode di form hanya `last_seq + 1` tanpa lock; kode final ditentukan saat simpan (bisa berbeda kalau ada yang menyimpan lebih dulu).

## Validasi & pesan (§6)
| Kondisi | Pesan |
|---|---|
| kategori wajib serial, serial kosong | Nomor seri wajib |
| serial sudah dipakai aset lain | Nomor seri duplikat |
| jumlah < 1 | Jumlah kosong |
| tanggal beli > hari ini | Tanggal di masa depan |
| garansi < tanggal beli | Garansi lebih awal dari tanggal beli |
| kondisi tidak cocok kategori | Kondisi tidak berlaku untuk kategori ini |
| edit kategori/jumlah/serial/cabang pada aset bertransaksi | Field tidak bisa diubah karena aset sudah punya riwayat |

## Perubahan data
- `assets`: baris baru; `categories.last_seq` +1.
- Tidak ada transaksi. Status tampil "Belum diserahkan"; aset langsung muncul di dropdown surat.

## Hapus
Hanya aset tanpa transaksi, tanpa item draft, tanpa lampiran — dihapus **permanen** (jejak di activity_log), supaya nomor serinya bisa dipakai lagi. Aset yang sudah pernah keluar tidak dihapus — gunakan [06-dilepas.md](06-dilepas.md).

**Uji:** 8.
