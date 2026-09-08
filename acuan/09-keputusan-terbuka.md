# 09 — Keputusan Terbuka

| # | Hal | Usulan |
|---|---|---|
| 1 | Kode 3 huruf cabang | JKT, BTM, MDN, PLM, CKR, SMG, SBY, SMD, BPN, BJM, MND, MKS, KDI |
| 2 | Cabang tiap karyawan pada data awal | semua Jakarta, koreksi setelah go-live |
| 3 | Admin cabang menerbitkan surat untuk aset cabang lain | tidak; admin pusat boleh, dan cabang aset mengikuti penerima (aturan §3a) |
| 4 | Pemakai dicetak di surat sebagai "Dipakai oleh: …" | ya |
| 5 | Segmen `IT` pada nomor surat konstan atau mengikuti divisi penerbit | konstan |
| 6 | Margin persis surat dan file logo kop | perlu file dari tim |
| 7 | Tanda tangan digital / approval penerima | di luar lingkup |
| 8 | Hapus transaksi | mutlak hanya koreksi |
| 9 | Hosting: PHP 8.5 dan ekstensi tersedia? | cek sebelum tahap 0 |
| 10 | Opsi A (Filament) vs B (Livewire + Blade) | A, bila tahap 0 lulus |
| 11 | Relasi "lisensi terpasang di aset X" (`installed_on_asset_id`) | tunda; sekarang catatan |
| 12 | Peringatan tanggal transaksi lebih awal dari riwayat terakhir: tolak atau hanya peringatan | peringatan; admin pusat boleh lanjut |
| 13 | Stock opname (pemeriksaan fisik berkala: petugas mengonfirmasi tiap aset ada/tidak, hasilnya jadi status Hilang) | tahap lanjutan; skema saat ini sudah cukup (tabel `stock_takes` + `stock_take_items`), tidak perlu mengubah ledger |
| 15 | Status aset tambahan buatan pengguna (mis. "Dipinjam"): diizinkan dengan flag; dibatasi admin pusat? | admin pusat saja |
| 14 | Pindahkan ke orang lain: lewat gudang (dua langkah) atau langsung A→B dengan Pihak Pertama = A | langsung A→B sebagai default (surat sesuai kenyataan), lewat gudang tetap tersedia |
