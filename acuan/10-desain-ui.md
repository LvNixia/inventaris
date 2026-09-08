# 10 — Desain UI: Responsif, Palet, Komponen

Arah: **minimalis, mudah dipakai, pastel biru muda**. Satu aksen, banyak ruang kosong, teks gelap di atas putih kebiruan. Tidak ada gradien, tidak ada emoji, ikon garis satu gaya. Wireframe halaman utama ada di kanvas desain (lihat README).

## 1. Breakpoint & tata letak

| Nama | Lebar | Navigasi | Konten |
|---|---|---|---|
| Mobile | < 768 px | **Bottom bar** 5 tombol (Beranda, Aset, Surat, Riwayat, Menu); topbar berisi judul halaman + pencarian/aksi utama | Satu kolom; tabel menjadi **kartu**; form satu kolom; aksi utama sebagai tombol lebar di bawah (sticky) |
| Tablet | 768–1279 px | **Sidebar ikon** 72 px (label muncul saat hover/tap), bisa dilebarkan | Dua kolom untuk form/detail; tabel tetap tabel dengan kolom sekunder disembunyikan |
| Desktop | ≥ 1280 px | **Sidebar penuh** 248 px dengan label | Konten maksimal 1200 px; detail aset 2 kolom (info kiri, riwayat kanan); tabel penuh |

Aturan umum:
- Semua target sentuh ≥ 44 px tinggi; jarak antar tombol ≥ 8 px.
- Ukuran font dasar 15 px di mobile, 14 px di desktop; tidak ada teks < 12 px.
- Tidak ada scroll horizontal halaman. Tabel yang lebih lebar dari layar dibungkus `overflow-x: auto` di tablet; di mobile diganti kartu.
- Filter di mobile/tablet masuk ke **lembar bawah (bottom sheet)** yang dibuka tombol "Filter (n)"; di desktop ditampilkan sebagai baris chip di atas tabel.
- Dialog konfirmasi: di mobile jadi bottom sheet, di desktop modal 480 px.
- Keyboard: input angka memicu keypad numerik (`inputmode="numeric"`), tanggal memakai picker bawaan browser.

## 2. Perilaku komponen per layar

| Komponen | Mobile | Tablet | Desktop |
|---|---|---|---|
| Daftar aset | kartu: kode + nama (tebal), baris kedua: serial · pemegang, kanan: badge status; ketuk → detail | tabel: kode, nama, pemegang, status, sisa; kolom cabang/garansi disembunyikan | tabel penuh + kolom garansi, kategori, cabang |
| Detail aset | tab: Info · Riwayat · Surat · Lampiran; tombol aksi dalam sheet "Aksi" | info + riwayat bertumpuk; aksi sebagai baris tombol | dua kolom; aksi di kanan atas |
| Form surat | langkah: 1 Pihak → 2 Barang → 3 Tinjau; baris barang sebagai kartu dengan stepper jumlah; tombol "Tambah barang" membuka pencarian layar penuh | form satu halaman, baris barang sebagai tabel ringkas | form satu halaman; header pihak di kiri, barang di kanan |
| Dashboard | angka ringkasan 2 kolom; grafik satu per baris | 3 kolom | 4–6 kolom; grafik berdampingan |
| Pencarian barang (dropdown) | layar penuh dengan input di atas, hasil sebagai kartu (kode · nama · serial · sisa) | dropdown lebar penuh | dropdown 480 px |
| Preview PDF | buka di tab baru | panel samping | panel samping 40% |

## 3. Palet (pastel biru muda)

Definisikan sebagai CSS custom properties dan, di Filament, sebagai warna panel (`->colors([...])`). Nilai dipilih dengan chroma rendah dan seragam supaya harmonis.

| Token | Hex | Peran |
|---|---|---|
| `--bg` | `#F4F8FC` | latar halaman (putih kebiruan) |
| `--surface` | `#FFFFFF` | kartu, tabel, form |
| `--surface-2` | `#EAF2FA` | header tabel, hover baris, chip |
| `--border` | `#D9E5F1` | garis pemisah, outline input |
| `--primary` | `#7FB3E6` | aksen: link aktif, fokus, ikon terpilih |
| `--primary-soft` | `#CFE3F7` | latar tombol utama, badge info, nav aktif |
| `--primary-ink` | `#1F3A5F` | teks di atas `primary-soft`, judul |
| `--ink` | `#1E2A38` | teks utama |
| `--ink-muted` | `#6B7A8C` | teks sekunder, label |
| `--success-soft` / `--success-ink` | `#D6F0E3` / `#1F6B4A` | badge Tersedia, Aktif |
| `--warning-soft` / `--warning-ink` | `#FBE8CF` / `#8A5A1E` | badge Hampir habis, Dalam Perjalanan, Servis |
| `--danger-soft` / `--danger-ink` | `#F9D9DC` / `#9B2C35` | badge Habis, Rusak, tombol Batalkan |
| `--neutral-soft` | `#E6ECF2` | badge Dilepas, draft |

Aturan pemakaian: tombol utama = `primary-soft` latar + `primary-ink` teks (bukan biru pekat); hanya **satu** tombol utama per layar. Tombol sekunder = putih dengan border. Tombol destruktif = `danger-soft`. Badge selalu pasangan `*-soft`/`*-ink`. Bayangan hampir tidak ada (`0 1px 2px rgba(31,58,95,.06)`); pemisahan lewat border dan latar, bukan bayangan.

Mode gelap: tidak dibuat pada tahap ini (Filament menyediakan bawaan; nonaktifkan lewat `->darkMode(false)` supaya palet tetap konsisten).

## 4. Tipografi

- Font: **Plus Jakarta Sans** (Google Fonts) untuk judul dan teks; fallback `system-ui, -apple-system, "Segoe UI", sans-serif`. Angka tabel memakai `font-variant-numeric: tabular-nums`.
- Skala: judul halaman 22/28 semibold; judul kartu 16/22 semibold; teks 14/20 (mobile 15/22); label & badge 12/16 medium; angka ringkasan 28/32 semibold.
- Kode aset ditampilkan monospace ringan (`ui-monospace, "JetBrains Mono", monospace`, 13 px) agar mudah dibaca dan disalin.

## 5. Komponen inti

| Komponen | Ketentuan |
|---|---|
| Tombol | tinggi 44 px (mobile) / 40 px (desktop), radius 10 px, padding 0 16 px, ikon 20 px di kiri bila ada |
| Input | tinggi 44/40 px, radius 10 px, border `--border`, fokus: border `--primary` + ring 3 px `primary-soft`; label di atas, teks bantuan/kesalahan di bawah 12 px |
| Kartu | radius 14 px, padding 16 px, border 1 px `--border`, tanpa bayangan |
| Badge status | radius 999 px, padding 2 10 px, 12 px medium |
| Tabel | header `--surface-2`, baris 48 px, zebra tidak dipakai, hover `--surface-2`; kolom aksi paling kanan berupa ikon |
| Angka ringkasan (stat) | kartu: label 12 px muted, angka 28 px, keterangan 12 px; tanpa ikon dekoratif |
| Empty state | satu kalimat + satu tombol; tanpa ilustrasi |
| Toast | kanan bawah (desktop) / atas (mobile), 4 detik, latar `--primary-ink` teks putih |
| Ikon | Lucide (stroke 1.75, 20 px); satu set saja |

## 6. Pemetaan ke Filament (Opsi A)

- Warna panel: `->colors(['primary' => Color::hex('#7FB3E6'), 'gray' => Color::Slate, 'success' => Color::hex('#1F6B4A'), 'warning' => Color::hex('#8A5A1E'), 'danger' => Color::hex('#9B2C35')])`; latar dan permukaan lewat CSS statis kecil yang didaftarkan `FilamentAsset::register()` (menimpa variabel `--gray-50` dst. ke palet di atas). Tanpa `make-theme`.
- Font: `->font('Plus Jakarta Sans')` (Filament memuat dari Google Fonts).
- Sidebar: `->sidebarCollapsibleOnDesktop()`; di tablet Filament otomatis menjadi ikon; di mobile Filament memakai menu hamburger + drawer — **bottom bar tidak ada bawaan**; implementasikan sebagai `renderHook(PanelsRenderHook::BODY_END)` yang menyisipkan komponen Blade bottom bar (tampil hanya < 768 px) dan menyembunyikan tombol hamburger. Kalau dianggap terlalu banyak kustomisasi, terima drawer bawaan Filament di mobile — fungsinya sama, hanya satu ketukan lebih jauh.
- Tabel → kartu di mobile: Filament Table mendukung `->contentGrid(['md' => 1, 'xl' => 1])` dengan `Split`/`Stack` layout untuk tampilan kartu di bawah breakpoint; pakai itu, bukan komponen terpisah.
- Form surat langkah-demi-langkah di mobile: Filament `Wizard` dengan tiga step; di desktop tampilkan sebagai satu halaman (`->skippable()` + tampilan bersyarat berdasarkan lebar layar tidak tersedia server-side, jadi pilih **Wizard di semua ukuran** untuk konsistensi, dengan langkah yang bisa dilompati).
- Widget dashboard: `StatsOverviewWidget` dengan `columns` responsif; chart widget memakai Chart.js bawaan Filament, warna seri dari palet (`primary`, `primary-soft`, `neutral-soft`).

## 7. Aksesibilitas
Kontras teks ≥ 4.5:1 (semua pasangan `*-ink` di atas `*-soft` sudah memenuhi; cek `#6B7A8C` di atas putih = 4.6:1). Fokus terlihat (ring). Semua ikon-tombol punya `aria-label`. Badge tidak mengandalkan warna saja: selalu ada teks.
