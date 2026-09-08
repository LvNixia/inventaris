# 02 — Buat Draft & Terbitkan Surat Serah Terima

**Tujuan:** menyerahkan satu atau lebih barang kepada seseorang dengan bukti surat; transaksi Keluar dibuat otomatis.
**Siapa:** admin pusat; admin cabang (surat cabangnya).
**Prasyarat:** aset memenuhi §4 (tersedia, status boleh diserahkan, cabang sama); penerima dan penanda tangan ada di master karyawan.

## Langkah di UI
1. Surat Serah Terima → **Buat**. Cabang terisi otomatis (admin cabang) / dipilih (admin pusat). Tanggal default hari ini.
2. Pilih **Pihak Pertama** (default: karyawan yang tertaut ke akun), **Pihak Kedua**, **Mengetahui** (hanya karyawan ber-flag witness). Jabatan & divisi tampil otomatis.
3. **Tambah barang**: dropdown pencarian (`KODE | Merk Model | Serial`) hanya berisi aset yang memenuhi §4. Per baris: jumlah (default 1, maks `qty_available`, sisa ditampilkan), **Pemakai** (opsional; dipakai bila barang untuk orang lain selain Pihak Kedua), keterangan tambahan.
4. **Simpan draft** → halaman detail dengan tombol **Preview PDF** (bertanda DRAFT, tanpa nomor) dan **Terbitkan**.
5. **Terbitkan** → konfirmasi → nomor surat muncul, PDF tersimpan, tombol **Unduh PDF**. Surat tidak bisa diedit lagi.

## Proses di server (`HandoverService`)
Draft: simpan `handover_documents` (`status = draft`, `document_number = null`) dan `handover_items` tanpa snapshot; **tidak menyentuh stok**.

Terbitkan:
```
DB::transaction:
  doc = HandoverDocument::lockForUpdate()->find(id) ; pastikan status = draft
  assets = Asset::lockForUpdate()->whereIn(id, item asset_ids)   // kunci semua aset sekaligus, urut id (hindari deadlock)
  untuk tiap item:
      validasi: qty_available >= quantity ; status transferable ; branch cocok (admin cabang) 
      kumpulkan error per baris → bila ada, rollback & tampilkan semua error
  counter = DocumentCounter::lockForUpdate()->firstOrCreate(branch, year, month)
  doc.document_number = "IDS-IT/{code}/{YYYY}/{MM}/{NN}" ; counter.last_no++
  snapshot para pihak (nama, jabatan, divisi) ke doc
  untuk tiap item:
      snapshot item_name, serial, specifications, condition, remarks (+ "Dipakai oleh: X")
      AssetTransaction::create(type=handover, direction=out, status=Dipakai,
          from=first_party, to=second_party, user=item.user_employee_id,
          quantity, handover_document_id=doc.id, date=doc.document_date)
      if item.transfer_from (mode langsung A→B, lihat alur 04):
          AssetTransaction::create(type=return, direction=in, status=Spare/Gudang, from=A, to=first_party, quantity)  // dibuat SEBELUM handover
      if second_party.branch_id != asset.branch_id (hanya admin pusat):
          asset.branch_id = second_party.branch_id
          AssetTransaction::create(type=branch_transfer, direction=neutral, status=Dipakai, from_branch, to_branch, to=second_party)
      StockCalculator::recompute(asset)
  pdf = PdfRenderer::handover(doc)  → storage/app/private/handovers/{Y}/{uuid}.pdf
  doc.status = issued ; issued_at = now ; pdf_path
  activity log
```
Render PDF di dalam transaction supaya gagal render = surat tidak terbit. Kalau render lambat (> 2 detik), pindahkan ke job setelah commit dengan status sementara `issued` + `pdf_path` null dan tombol "PDF sedang dibuat".

## Validasi & pesan
| Kondisi | Pesan (per baris) |
|---|---|
| jumlah > sisa | Melebihi sisa tersedia (sisa: n) |
| aset sudah diserahkan lewat surat lain sejak draft dibuat | IDS-LAP-004 sudah diserahkan lewat surat IDS-IT/JKT/2026/09/03 |
| status aset tidak boleh diserahkan | Status {status} tidak bisa diserahkan |
| aset sama dua kali | Barang ini dipilih lebih dari sekali |
| Pihak Pertama = Pihak Kedua | Penyerah dan penerima tidak boleh sama |
| tidak ada barang | Surat harus memuat minimal satu barang |
| tanggal surat di masa depan | Tanggal surat tidak boleh di masa depan |
| tanggal surat lebih awal dari surat terakhir cabang di bulan yang sama | (peringatan) Nomor urut akan tidak sesuai urutan tanggal |
| admin cabang memilih penerima cabang lain | Penerima harus dari cabang Anda |
| aset di draft sudah dihapus | Barang tidak ada lagi; hapus baris ini |

Nomor surat memakai tahun-bulan dari `document_date`, bukan tanggal terbit. Draft **tidak mengunci stok** (§8): dua draft boleh memuat aset yang sama; yang terbit dulu menang. Daftar draft menampilkan ikon peringatan bila ada aset yang sudah tidak tersedia.

## Perubahan data
- `handover_documents`: `status issued`, nomor, snapshot, `pdf_path`.
- `handover_items`: snapshot terisi.
- `asset_transactions`: satu baris `handover` per item.
- `assets`: `qty_out` +n, `qty_available` −n, `current_holder = Pihak Kedua`, `current_user = pemakai`, `current_status = Dipakai`.
- `document_counters.last_no` +1.

## Hapus draft
Draft boleh dihapus permanen oleh pembuatnya atau admin pusat; tidak menyentuh stok. Aset yang menjadi item di draft mana pun tidak bisa dihapus (pesan: "Aset ada di draft surat #n").

## Setelah terbit
Hanya dua aksi: Unduh PDF, Batalkan ([07](07-batalkan-surat.md)). Perubahan isi = batalkan lalu buat baru.

**Uji:** 1, 4, 6, 9, 13.
