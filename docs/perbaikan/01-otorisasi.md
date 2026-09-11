# 01 — Otorisasi

## Masalah

Tidak ada satu pun Policy di aplikasi ini, dan pintu masuknya hanya memeriksa
satu hal:

```php
// app/Models/User.php
public function canAccessPanel(Panel $panel): bool
{
    return (bool) $this->is_active;
}
```

Peran `Viewer` tidak pernah diperiksa di mana pun kecuali di definisi enumnya
sendiri. Akibatnya siapa pun yang bisa masuk dapat menghapus aset, menerbitkan
surat serah terima, dan mencatat pembayaran vendor.

Terbukti saat penelusuran: akun berperan Peninjau berhasil menyetujui pesanan
pembelian. Pintu itu sudah ditutup, sisanya belum.

`BranchScope` memang membatasi **baris mana** yang terlihat, tetapi tidak
membatasi **tindakan apa** yang boleh dilakukan atas baris itu.

## Matriks yang diusulkan

| | Admin Pusat | Admin Cabang | Peninjau |
|---|---|---|---|
| Lihat semua modul | ✅ seluruh cabang | ✅ cabangnya | ✅ cabangnya |
| Aset, serah terima, servis, pindah cabang | ✅ | ✅ | ❌ |
| Pesanan: buat & ajukan | ✅ | ✅ | ❌ |
| Pesanan: setujui | ✅ | ✅ sampai batas | ❌ |
| Penerimaan barang | ✅ | ✅ | ❌ |
| Faktur & pembayaran vendor | ✅ | ❌ | ❌ |
| Data acuan (Referensi) | ✅ | ❌ | ❌ |
| Cabang, divisi, jabatan | ✅ | ❌ | ❌ |
| Pengguna | ✅ | ❌ | ❌ |
| Impor aset | ✅ | ❌ | ❌ |
| Laporan & Pemeriksaan Data | ✅ | ✅ | ✅ |

Dua keputusan yang perlu Anda tegaskan sebelum ini dikerjakan:

1. **Bolehkah Admin Cabang mencatat faktur dan pembayaran?** Usulan di atas:
   tidak — pembayaran terpusat. Kalau cabang Batam membayar vendornya sendiri,
   ubah menjadi boleh untuk cabangnya.
2. **Bolehkah Admin Cabang mengubah data acuan** (merek, kategori, kondisi)?
   Usulan: tidak. Data acuan dipakai bersama; satu cabang menambah merek
   duplikat akan mengotori seluruh katalog.

## Rancangan teknis

Membuat 25 berkas Policy untuk 25 model adalah pekerjaan yang sebagian besar
salinan. Yang diusulkan: **satu policy dasar + peta kemampuan**.

```
app/Policies/BasePolicy.php        aturan umum: viewAny, view, create, update, delete
app/Policies/Kemampuan.php         peta peran → modul → tindakan (satu berkas, mudah dibaca)
```

`BasePolicy` menjawab dengan membaca peta, bukan dengan logika per model:

```php
public function create(User $user): bool
{
    return Kemampuan::boleh($user->role, $this->modul(), 'create');
}
```

Pendaftarannya lewat `Gate::guessPolicyNamesUsing()` di `AppServiceProvider`,
sehingga seluruh model memakai `BasePolicy` tanpa satu pun berkas tambahan.
Model yang perlu aturan khusus — misalnya `PurchaseOrder` dengan batas nilai —
mendapat policy sendiri yang mewarisi `BasePolicy`.

### Catatan penting soal Filament

Filament memperlakukan **tidak adanya policy sebagai "boleh"**. Itu sebabnya
lubang ini tidak pernah terlihat. Setelah `guessPolicyNamesUsing` dipasang,
seluruh modul langsung tunduk pada peta kemampuan — termasuk yang belum sempat
dipikirkan. Karena itu peta harus lengkap sebelum dipasang, bukan sesudah.

### Halaman kustom

Empat halaman di luar Resource perlu penjagaannya sendiri, lewat
`canAccess()`:

| Halaman | Boleh diakses |
|---|---|
| Impor Aset | Admin Pusat |
| Pemeriksaan Data | Admin Pusat, Admin Cabang |
| Laporan (6 halaman) | semua peran |
| Panduan | semua peran |

### Tindakan di dalam tabel

Tombol seperti Setujui, Batalkan, dan Terbitkan tidak tercakup policy standar.
Semuanya sudah punya `->visible()`; yang perlu ditambahkan adalah penjagaan di
service-nya, karena tombol tersembunyi bukan penjagaan — hanya kerapian.

Aturan yang sudah dijaga di service: persetujuan pesanan, pembatalan pesanan,
pembatalan penerimaan, pembatalan faktur, alokasi pembayaran. Yang belum:
penerbitan surat serah terima, pembatalan serah terima, pemindahan cabang,
pelepasan aset.

## Pengujian

| Pengujian | Memastikan |
|---|---|
| Peninjau tidak bisa membuat, mengubah, atau menghapus aset | Peran baca-saja benar-benar baca-saja |
| Peninjau tetap bisa membuka laporan | Pembatasan tidak kebablasan |
| Admin Cabang tidak bisa membuka modul Faktur | Pemisahan tugas keuangan |
| Admin Cabang tidak bisa mengubah data acuan | Katalog bersama tidak terkotori |
| Admin Cabang hanya melihat asetnya sendiri | `BranchScope` tetap berlaku |
| Admin Pusat bisa segalanya | Tidak ada yang terkunci untuk pemilik sistem |

Enam pengujian ini menempel pada matriks di atas. Kalau matriksnya berubah,
pengujiannya berubah — itu gunanya ditulis lebih dulu.

## Risiko

Memasang policy secara menyeluruh berarti **setiap modul yang terlewat dari
peta akan tertutup**, bukan terbuka. Itu arah gagal yang benar, tetapi bisa
mengunci pekerjaan sehari-hari kalau petanya kurang lengkap. Karena itu
langkah pertama setelah pemasangan adalah menjalankan seluruh alur sebagai
ketiga peran, bukan hanya menjalankan tes.
