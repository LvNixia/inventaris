# 04 — Pindahkan ke Orang Lain

**Tujuan:** memindahkan barang dari pemegang A ke pemegang B dengan surat, tanpa dua langkah manual.
**Siapa:** admin pusat; admin cabang (cabangnya).
**Prasyarat:** aset dipegang A (saldo > 0); B ada di master.

Ada dua mode. **Mode langsung** adalah default karena suratnya sesuai kenyataan (A menyerahkan ke B, IT sebagai Mengetahui).

## Mode langsung A → B
### Langkah di UI
1. Detail aset → **Pindahkan ke orang lain** → pilih B (dan pemakai bila perlu), Mengetahui, tanggal, kondisi saat ini.
2. Sistem membuat **draft surat** dengan Pihak Pertama = **A**, Pihak Kedua = B, item = aset ini dengan penanda `transfer_from = A`.
3. Preview → **Terbitkan** ([02](02-terbitkan-surat.md)).

### Proses di server
Sama dengan terbitkan surat; untuk item ber-`transfer_from`, `HandoverService` membuat **dua** transaksi berurutan di dalam transaction yang sama:
```
AssetTransaction::create(type=return,   direction=in,  status=Spare/Gudang, from=A, to=A, quantity)   // id lebih kecil
AssetTransaction::create(type=handover, direction=out, status=Dipakai,      from=A, to=B, quantity, handover_document_id)
```
Saldo A turun n, saldo B naik n, `qty_available` tidak berubah pada akhirnya. Selama draft belum terbit, tidak ada yang berubah — dan itu benar, karena barang memang masih di A.

Beberapa aset sekaligus dari A ke B: pilih di daftar (semua dipegang A) → aksi massal → satu draft.

## Mode lewat gudang
Untuk kasus barang benar-benar kembali ke IT dulu (dicek/diinstal ulang) sebelum diserahkan lagi:
1. [03 Tarik kembali](03-tarik-kembali.md) (final, dengan kondisi saat diterima).
2. [02 Terbitkan surat](02-terbitkan-surat.md) ke B, Pihak Pertama = petugas.

## Validasi & pesan
Gabungan [02](02-terbitkan-surat.md) dan [03](03-tarik-kembali.md). Tambahan:
| Kondisi | Pesan |
|---|---|
| A = B | Pemegang lama dan baru sama |
| saldo A < jumlah saat terbit (A sudah mengembalikan sebagian) | Pemegang lama hanya memegang n unit |
| B di cabang lain (admin cabang) | Penerima harus dari cabang Anda |

## Perubahan data
- Mode langsung: `return` + `handover` pada saat terbit; pemegang akhir B; surat menampilkan A sebagai Pihak Pertama.
- Mode gudang: `return` (final) lalu `handover` saat surat terbit.

**Uji:** 3 (mode langsung), 3b: mode gudang.
