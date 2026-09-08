# 11 — Master Data (daftar yang bisa ditambah, diubah, dihapus)

Prinsip: **setiap kosakata bisnis adalah master yang dikelola dari UI**, bukan enum di kode. Enum di kode hanya untuk hal yang punya makna mesin (arah stok, tipe transaksi, peran, status dokumen). Master yang perlu dipahami kode memakai kolom `code` (tetap) + `is_system`; kode merujuk `code`, pengguna bebas mengubah `name`.

## 1. Daftar master

| Master | Tabel | Kolom yang bisa diedit | Kolom sistem | Dipakai di |
|---|---|---|---|---|
| Cabang | `branches` | `name`, `address`, `phone`, `is_active` | `code` (3 huruf; dipakai di nomor surat — tidak bisa diubah setelah ada surat) | aset, karyawan, surat, nomor surat |
| Divisi | `divisions` | `name`, `is_active` | — | karyawan |
| Jabatan | `positions` (**baru**, menggantikan teks bebas `employees.position`) | `name`, `is_active` | — | karyawan, snapshot surat |
| Karyawan | `employees` | `name`, `nik`, `position_id`, `division_id`, `branch_id`, `can_sign_as_witness`, `is_active` | — | semua transaksi |
| Kategori aset | `categories` | `name`, `requires_serial`, `sort_order`, `is_active` | `code_prefix` (tidak bisa diubah setelah ada aset), `last_seq` | aset, kode aset |
| Merk | `brands` | `name`, `is_active` | — | aset |
| Kondisi | `conditions` | `name`, `applies_to`, `sort_order`, `is_active` | `code` untuk yang sistem: `baik`, `hilang` | aset, transaksi, servis |
| Status aset | `asset_statuses` | `name`, `sort_order` | `code` + `stock_direction`, `transferable`, `requires_qty`, `clears_holder`, `is_system` — 7 status bawaan `is_system = true`: `in_use`, `spare`, `service`, `broken`, `disposed`, `registered`, `in_transit`. Status **baru** boleh ditambah dengan mengatur keempat flag; flag status sistem hanya bisa diubah admin pusat dengan peringatan | transaksi, filter |
| Alasan dilepas | `disposal_reasons` (**baru**) | `name`, `sort_order`, `is_active` | `code` + flag `is_lost` (alasan yang boleh dipakai untuk unit yang masih dipegang, alur 06) — bawaan: dijual, dihibahkan, dibuang, hilang (`is_lost`), lainnya | alur 06 |
| Jenis servis | `service_kinds` (**baru**) | `name`, `sort_order`, `is_active` | `code` + flag `changes_spec` (menampilkan bagian perubahan spesifikasi) — bawaan: perbaikan, upgrade (`changes_spec`), perawatan | alur 14 |
| Hasil servis | `service_results` (**baru**) | `name`, `sort_order`, `is_active` | `code` + flag `applies_spec` (hasil yang memberlakukan `spec_after`) dan `marks_broken` (menyarankan status Rusak) — bawaan: diperbaiki, ganti part (`applies_spec`), tidak bisa diperbaiki (`marks_broken`), dibatalkan | alur 14 |
| Vendor | `vendors` (**baru**, menggantikan teks bebas `assets.vendor` dan `asset_services.vendor_name`) | `name`, `type` (`toko`/`servis`/`keduanya`), `contact`, `phone`, `address`, `notes`, `is_active` | — | pembelian aset, servis |
| Jenis lampiran | `attachment_types` (**baru**) | `name`, `is_active` | `code`: `photo`, `invoice`, `warranty`, `service_receipt`, `other` (sistem) | lampiran |
| Pengguna | `users` | `name`, `email`, `role`, `branch_id`, `employee_id`, `is_active`, reset password | — | login |

Tetap **enum di kode** (bukan master): `stock_direction`, `asset_transactions.type`, `handover_documents.status`, `import_batches.status`, `users.role`, `conditions.applies_to`, `vendors.type`.

## 2. Aturan tambah / ubah / hapus (berlaku untuk semua master)

| Aksi | Aturan |
|---|---|
| Tambah | Nama wajib, unik per master (case-insensitive, trim) di dalam cabang untuk karyawan, global untuk lainnya. Entri baru langsung muncul di dropdown. Untuk master ber-`code`: `code` dibuat otomatis dari nama (slug) dan tidak ditampilkan kecuali di form lanjutan |
| Ubah | Nama boleh diubah kapan saja; **riwayat lama ikut menampilkan nama baru** (relasi FK) kecuali data yang sudah di-snapshot (item surat, jabatan di surat) — itu memang sengaja beku. Kolom sistem (`code`, prefix, flag arah stok) dikunci setelah dipakai; admin pusat bisa membukanya dengan dialog peringatan yang menyebut jumlah data terdampak |
| Hapus | Diizinkan **hanya bila belum dipakai** (tidak ada FK yang merujuk). Bila sudah dipakai → tombol Hapus berubah jadi **Nonaktifkan**: entri hilang dari dropdown, tetap tampil di data lama, bisa diaktifkan lagi. Entri `is_system` tidak bisa dihapus maupun dinonaktifkan |
| Gabung (merge) | Untuk merk, vendor, divisi, jabatan, kondisi: aksi "Gabungkan ke …" memindahkan semua rujukan ke entri tujuan lalu menghapus sumber — menyelesaikan duplikat akibat salah ketik ("Lenovo" dan "Lenovo ") tanpa kehilangan riwayat. Admin pusat saja, tercatat di audit |
| Urutan | Master yang punya `sort_order` bisa di-drag untuk mengatur urutan dropdown |
| Cepat-tambah | Dropdown karyawan, merk, vendor, dan jabatan di form punya tombol "+ Tambah baru" yang membuka modal kecil tanpa meninggalkan form (Filament: `createOptionForm`) |
| Import | Semua master bisa diimport lewat template (alur 12), upsert berdasarkan nama |
| Hak akses | Admin pusat: semua master. Admin cabang: tambah/ubah karyawan cabangnya, tambah merk/vendor/jabatan (tidak menghapus, tidak mengubah master ber-flag sistem). Viewer: tidak ada |

## 3. Dampak ke skema (perubahan dari versi sebelumnya)
- `employees.position` → `position_id` FK `positions`.
- `assets.vendor` → `vendor_id` FK `vendors` (nullable); `asset_services.vendor_name/contact` → `vendor_id`.
- `asset_transactions` mendapat `disposal_reason_id` (nullable) untuk `type = disposal`.
- `asset_services.kind` → `service_kind_id`; `result` → `service_result_id`.
- `asset_attachments.type` → `attachment_type_id`.
- `asset_statuses`, `conditions`, `disposal_reasons`, `service_kinds`, `service_results`, `attachment_types` mendapat `code` (unik), `is_system` (bool), `is_active`, `sort_order`.
- Kode aplikasi merujuk status/kondisi/alasan lewat `code` (mis. `AssetStatus::byCode('spare')`), **tidak pernah** lewat nama atau id tetap. Seeder membuat entri sistem dengan `updateOrCreate(['code' => …])`.

## 4. Halaman UI
Menu **Master & Pengaturan** → satu halaman per master, semuanya memakai pola yang sama (tabel + pencarian + tambah/ubah lewat modal + nonaktif/hapus + gabung). Entri sistem ditandai ikon kunci dan tooltip "bawaan sistem: nama bisa diubah, tidak bisa dihapus". Detail alur: [alur/15-kelola-master.md](alur/15-kelola-master.md).
