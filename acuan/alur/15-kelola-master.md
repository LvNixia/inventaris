# 15 — Kelola Master Data

**Tujuan:** menambah, mengubah, menonaktifkan, menghapus, dan menggabungkan entri master (cabang, divisi, jabatan, karyawan, kategori, merk, kondisi, status aset, alasan dilepas, jenis & hasil servis, vendor, jenis lampiran, pengguna). Daftar lengkap dan aturannya: [../11-master-data.md](../11-master-data.md).
**Siapa:** admin pusat (semua); admin cabang (terbatas, lihat §2 dokumen 11).

## Langkah di UI
1. Master & Pengaturan → pilih master → tabel (nama, jumlah pemakaian, aktif/nonaktif, ikon kunci untuk entri sistem).
2. **Tambah** → modal: nama (+ kolom khusus master itu, mis. prefix kategori, flag status) → Simpan.
3. **Ubah** → modal yang sama; kolom sistem terkunci bila sudah dipakai, dengan tautan "buka kunci" (admin pusat) yang menampilkan jumlah data terdampak.
4. **Hapus** → hanya tampil bila pemakaian = 0; bila > 0 tombolnya **Nonaktifkan**.
5. **Gabungkan ke …** (merk, vendor, divisi, jabatan, kondisi) → pilih entri tujuan → konfirmasi menampilkan jumlah data yang akan dipindahkan → Proses.
6. Dari form lain (aset, surat, servis): dropdown karyawan/merk/vendor/jabatan punya "+ Tambah baru" → modal ringkas → entri terpilih otomatis.

## Proses di server (`MasterService`)
```
create : validasi unik (lower(trim(name)) per scope) ; code = slug(name) bila master ber-code ; is_system=false
update : bila kolom sistem berubah dan usage>0 → tolak kecuali admin pusat + konfirmasi ; activity log nilai lama/baru
delete : usage = hitung FK dari semua tabel perujuk (didefinisikan per master di MasterRegistry) ; usage>0 → tolak ("Nonaktifkan") ; is_system → tolak
deactivate/activate : is_active toggle ; entri nonaktif disaring dari dropdown (scope active()) tetapi tetap ter-load di relasi lama
merge(source, target) : DB::transaction — untuk tiap tabel perujuk: UPDATE set fk=target where fk=source ; hapus source ; activity log "merged into"
```
`MasterRegistry` = satu tempat yang mendaftarkan, per master: model, kolom nama, scope keunikan, daftar (tabel, kolom FK) perujuk, kolom sistem, apakah bisa digabung. Halaman UI dan `Importer` membaca registry ini supaya menambah master baru di kemudian hari cukup satu entri.

## Validasi & pesan
| Kondisi | Pesan |
|---|---|
| nama duplikat | "{nama}" sudah ada (nonaktif?) → tawarkan aktifkan |
| hapus yang dipakai | Dipakai di n data; nonaktifkan saja |
| hapus/nonaktifkan entri sistem | Entri bawaan sistem tidak bisa dihapus |
| ubah prefix kategori yang sudah punya aset | Prefix dikunci; n aset memakai kode ini |
| ubah kode cabang yang sudah punya surat | Kode cabang dikunci; n surat memakai kode ini |
| gabung ke dirinya sendiri / ke entri nonaktif | Pilih entri tujuan lain |
| nonaktifkan karyawan yang memegang aset | lihat alur 10 |
| nonaktifkan cabang yang masih punya aset/karyawan aktif | Cabang masih punya n aset dan m karyawan |

## Perubahan data
Hanya tabel master yang bersangkutan (+ tabel perujuk saat gabung). Tidak pernah menyentuh ledger.

**Uji:** 31–34.
