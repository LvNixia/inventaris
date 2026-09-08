# 14 — Servis, Perbaikan & Upgrade

**Tujuan:** mencatat barang yang diservis/diperbaiki/di-upgrade secara lengkap: di mana fisiknya berada selama proses, biaya, hasil, garansi servis, dan **perubahan spesifikasi** bila di-upgrade — tanpa mengubah pemegang dan stok.
**Siapa:** admin pusat; admin cabang (cabangnya).
**Prasyarat:** aset ada (dipegang seseorang atau di gudang).

Status `Servis` (alur 05) hanya menandai "sedang tidak bisa dipakai/diserahkan". Detailnya hidup di **catatan servis** (`asset_services`), satu baris per kejadian, yang bisa berlangsung berhari-hari dan punya siklus sendiri: **dibuka → selesai / dibatalkan**.

## Jenis catatan
Jenis dan hasil servis adalah **master** (bisa ditambah/diubah di Master & Pengaturan; [../11-master-data.md](../11-master-data.md)). Bawaan:

| `code` | Contoh | Status aset selama proses |
|---|---|---|
| `repair` | layar pecah, keyboard rusak, tidak bisa nyala | Servis |
| `upgrade` (`changes_spec`) | tambah RAM, ganti SSD, ganti baterai, pasang lisensi | Servis bila barang ditinggal; tetap Dipakai bila dikerjakan di tempat < 1 hari (pilihan "barang tidak ditinggal") |
| `maintenance` | pembersihan, instal ulang, pengecekan berkala | idem |

## Langkah di UI — buka catatan servis
1. Detail aset → **Servis / Upgrade** → pilih jenis.
2. Isi: tanggal masuk; **dikerjakan oleh** (`internal` = IT sendiri / `vendor` = pilih dari master Vendor, dengan "+ Tambah baru"); **barang ditinggal?** (ya → status Servis; tidak → status tetap); keluhan/rencana pekerjaan; estimasi selesai; nomor tiket/referensi vendor; lampiran (foto kerusakan, tanda terima vendor).
3. Untuk jenis ber-flag `changes_spec` (bawaan: upgrade): bagian **Perubahan spesifikasi** — daftar spesifikasi saat ini ditampilkan sebagai baris yang bisa diedit/ditambah/dihapus; hasil akhirnya menjadi `spec_after`. Contoh: `8GB RAM DDR4` → `16GB RAM DDR4`.
4. **Simpan** → catatan berstatus `open`; bila "barang ditinggal", transaksi `status_change` → Servis dibuat otomatis dengan `notes` = ringkasan catatan; pemegang tetap (aturan §3).

## Langkah di UI — selesaikan
1. Detail aset (badge "Servis · di vendor X · sejak 3 hari") atau daftar Servis Berjalan → **Selesaikan**.
2. Isi: tanggal selesai; **hasil** (master Hasil servis — bawaan: diperbaiki, ganti part, tidak bisa diperbaiki, dibatalkan); pekerjaan yang dilakukan; biaya (jasa + part, boleh 0); **garansi servis sampai** (opsional); kondisi setelah; lampiran nota.
3. Untuk jenis `changes_spec` dengan hasil ber-flag `applies_spec`: konfirmasi `spec_after` (bisa dikoreksi) → **`assets.specifications` diperbarui**, `spec_before` tetap tersimpan di catatan.
4. Pilih **status setelah**: kembali ke pemegang (→ `Dipakai`, pemegang sama), ke gudang (→ `Spare / Gudang`; ini membuat transaksi `return` dari pemegang), atau tidak bisa diperbaiki (→ `Rusak`; bila dilepas, lanjut alur 06).
5. **Simpan** → catatan `closed`.

## Proses di server (`ServiceService`)
Buka:
```
DB::transaction:
  asset = lockForUpdate
  rec = AssetService::create(service_kind_id, performed_by, vendor_id, item_left,
        started_at, expected_at, ticket_ref, complaint, spec_before = asset.specifications,
        spec_after (upgrade, sementara), status=open, opened_by)
  if item_left: TransactionService::changeStatus(asset, Servis, notes="Servis #{rec.id}: {kind} · {vendor|internal}", service_id=rec.id)
  activity log
```
Selesaikan:
```
DB::transaction:
  asset = lockForUpdate ; rec = lockForUpdate (harus open)
  rec.finished_at, service_result_id, work_done, cost_service, cost_parts, service_warranty_until, condition_after_id, status=closed
  if kind.changes_spec and result.applies_spec: asset.specifications = rec.spec_after
  if condition_after_id: asset.condition_id = ...
  sesuai "status setelah":
     Dipakai        → TransactionService::changeStatus(asset, Dipakai, direction=neutral, service_id)   // pemegang sama
     Spare / Gudang → TransactionService::return(asset, from=current_holder, to=petugas, condition_after, service_id)
     Rusak          → TransactionService::changeStatus(asset, Rusak, service_id)
  StockCalculator::recompute(asset) ; activity log
```
Bila barang tidak ditinggal (`item_left = false`) dan status tidak berubah, tetap dibuat satu transaksi `status_change` dengan status yang sama dan `service_id` terisi, supaya riwayat aset memuat kejadian upgrade-nya (§3: transaksi dengan status sama diizinkan bila `service_id` terisi).

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| aset sudah punya catatan `open` | Masih ada servis berjalan (#n); selesaikan dulu |
| aset Dalam Perjalanan / Dilepas | Barang tidak bisa diservis pada status ini |
| selesai dengan "kembali ke pemegang" padahal aset tidak dipegang siapa pun | Barang di gudang; pilih Spare / Gudang |
| `finished_at < started_at` | Tanggal selesai lebih awal dari tanggal masuk |
| upgrade tanpa perubahan spesifikasi | (peringatan) Spesifikasi tidak berubah — lanjutkan sebagai perawatan? |
| biaya diisi tetapi hasil `cancelled` | (peringatan) Ada biaya pada servis yang dibatalkan |

## Perubahan data
- `asset_services`: satu baris; `spec_before`/`spec_after` untuk upgrade.
- `assets.specifications` diperbarui saat upgrade selesai; `condition_id` bila diisi.
- Transaksi `status_change`/`return` dengan `service_id` sebagai tautan.
- Tidak ada perubahan `quantity`, `qty_*`, `unit_price`. Biaya servis **tidak** dikapitalisasi ke harga aset; total biaya servis ditampilkan terpisah di detail aset dan dashboard ("Biaya servis tahun ini").

## Tampilan
- Detail aset: badge status "Servis" + baris info "di {vendor|IT} sejak {n} hari · estimasi {tanggal}"; tab **Servis** berisi daftar catatan (jenis, tanggal, vendor, biaya, hasil) dan untuk upgrade tampilan sebelum → sesudah.
- Halaman **Servis Berjalan** (menu Aset): semua catatan `open` di cabang, diurutkan yang paling lama; badge merah bila lewat estimasi.
- Bukti: **Tanda Terima Servis** (PDF satu halaman) saat barang diserahkan ke vendor — barang, kerusakan, kelengkapan yang ikut (charger, tas), nama vendor; dan versi "Pengambilan" saat selesai. Template mengikuti gaya bukti pengembalian (05-surat-pdf.md §2).

## Garansi vendor & garansi servis
- Bila `warranty_until` aset masih aktif saat catatan dibuka, tampilkan pengingat "Garansi pabrik masih aktif sampai …" agar tidak membayar servis yang seharusnya gratis.
- `service_warranty_until` ditampilkan di detail aset selama masih berlaku; bila kerusakan yang sama muncul lagi dalam masa itu, catatan baru menampilkan tautan ke catatan sebelumnya.

**Uji:** 27–30 (07-tahapan-dan-uji.md).
