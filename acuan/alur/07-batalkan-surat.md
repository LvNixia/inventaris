# 07 — Batalkan Surat

**Tujuan:** membatalkan surat yang terbit karena salah (salah orang, salah barang, batal diserahkan).
**Siapa:** admin pusat saja.
**Prasyarat:** surat `issued`, dan untuk tiap item:
- barang berseri (`quantity = 1`): transaksi terakhir aset adalah transaksi surat ini (belum bergerak lagi);
- barang massal: saldo Pihak Kedua untuk aset itu masih ≥ `item.quantity` (unit yang diserahkan belum dikembalikan). Pergerakan unit lain dari aset yang sama ke orang lain **tidak** menghalangi.

## Langkah di UI
1. Detail surat → **Batalkan** → sistem mengecek prasyarat dan menampilkan hasilnya per barang.
2. Bila lolos: isi alasan (wajib) → konfirmasi.
3. Bila ada barang yang sudah bergerak lagi: ditolak dengan daftar barang + transaksi penghalangnya, dan saran "buat transaksi koreksi" ([11](11-koreksi.md)).

## Proses di server (`HandoverCancellation`)
```
DB::transaction:
  doc = lockForUpdate ; pastikan status issued
  untuk tiap item: 
      asset = lockForUpdate
      if asset.quantity == 1: penghalang bila transaksi terakhir aset bukan transaksi surat ini
      else:               penghalang bila holderBalance(asset, second_party) < item.quantity
  if penghalang → rollback, tampilkan
  untuk tiap item:
      AssetTransaction::create(type=cancellation, direction=in, status=Spare/Gudang,
          from=second_party, to=first_party, quantity=item.quantity,
          handover_document_id=doc.id, notes="Pembatalan surat {nomor}: {alasan}")
      StockCalculator::recompute(asset)
  doc.status = cancelled ; cancelled_at ; cancel_reason
  PdfRenderer::handover(doc, watermark=DIBATALKAN) → simpan ke cancelled_pdf_path (PDF asli tetap disimpan sebagai arsip; UI menampilkan versi batal)
  activity log
```
Nomor surat tidak dipakai ulang; counter tidak dikurangi.

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| ada transaksi lanjutan / unit sudah dikembalikan | IDS-LAP-004 sudah bergerak setelah surat ini (transaksi #123, Tarik kembali 05/09/2026). Batalkan lewat koreksi |
| surat memuat item lintas cabang (§3a) | pembatalan juga membuat `branch_transfer` balik ke cabang asal |
| surat bukan `issued` | Hanya surat terbit yang bisa dibatalkan |
| alasan kosong | Alasan wajib diisi |

## Perubahan data
- `asset_transactions`: `cancellation` per item (arah `in`).
- `assets`: `qty_available` kembali, holder null, status Spare / Gudang.
- `handover_documents.status = cancelled`; PDF ber-watermark.

**Uji:** 11, 12.
