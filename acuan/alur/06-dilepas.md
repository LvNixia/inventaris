# 06 — Dilepas / Dijual

**Tujuan:** mengeluarkan unit dari inventaris secara permanen (dijual, dihibahkan, dibuang, hilang).
**Siapa:** admin pusat; admin cabang (cabangnya).
**Prasyarat:** unit yang dilepas ada di gudang (`qty_available ≥ jumlah`). Unit yang masih dipegang orang harus ditarik dulu — **kecuali alasan Hilang**, karena barang hilang tidak bisa "dikembalikan" lebih dulu.

## Langkah di UI
1. Detail aset → **Dilepas / Dijual**.
2. Isi tanggal, jumlah (berseri = 1; massal = jumlah unit yang dilepas, maks `qty_available`), alasan (dropdown dari master **Alasan dilepas** — bawaan: dijual, dihibahkan, dibuang, hilang, lainnya; bisa ditambah di Master & Pengaturan), catatan (nomor berita acara, pembeli, harga jual bila ada).
3. **Simpan** → konfirmasi "Unit tidak bisa dikembalikan ke stok; salah catat harus lewat koreksi".

Alasan ber-flag **`is_lost`** (bawaan: Hilang) pada unit yang dipegang seseorang: form menampilkan pemegang; sistem membuat dua transaksi sekaligus — `return` dari pemegang dengan catatan "Hilang saat dipegang {nama}" dan `condition_after = Condition::byCode('hilang')`, lalu `disposal`. Riwayat dengan jelas menunjukkan siapa yang memegang saat hilang.

## Proses di server (`TransactionService::dispose`)
```
DB::transaction:
  asset = lockForUpdate
  validasi quantity <= qty_available
  AssetTransaction::create(type=disposal, direction=writeoff, status=byCode('disposed'), disposal_reason_id, quantity, notes)
  StockCalculator::recompute(asset)   → qty_writeoff +n ; qty_available −n ; holder null ; status Dilepas
  activity log
```

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| jumlah > qty_available | Unit masih dipegang, tarik dulu (di gudang: n) |
| jumlah ≤ 0 | Jumlah harus lebih dari 0 |

## Perubahan data
- `assets.qty_writeoff` +n; `qty_available` −n. Baris aset **tidak dihapus**; unit aktif di dashboard = `quantity − qty_writeoff`.
- Barang massal yang dilepas sebagian: sisa unit tetap bisa diserahkan; status aset tampil "Dilepas / Dijual" hanya bila `qty_available = 0` dan tidak ada saldo pemegang — `StockCalculator` menangani: bila masih ada unit aktif, status mengikuti transaksi non-writeoff terakhir.

**Uji:** 19.
