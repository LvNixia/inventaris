# 02 — Kebijakan Pengadaan

## Vendor yang hilang

Penerimaan barang boleh disetujui tanpa vendor. Batch pembelian yang lahir
darinya ikut kosong, dan tidak ada satu pun tanda peringatan:

```
6. penerimaan tanpa vendor disetujui: GR/JKT/2026/09/03
   vendor batch: KOSONG | nomor nota: KOSONG
```

Yang hilang bukan angka, melainkan jawaban atas pertanyaan yang pasti muncul
setahun kemudian: *laptop ini dulu beli di mana, garansinya klaim ke siapa?*

### Usulan

**Jangan diwajibkan.** Hibah dan aset warisan memang tidak punya vendor;
mewajibkannya akan mendorong orang mengisi vendor asal-asalan, yang lebih buruk
daripada kosong.

Tambahkan sebagai pemeriksaan kesembilan di `DataCheckService`:

```php
protected function unitTanpaAsalPembelian(): array
```

Menemukan unit aktif yang batch pembeliannya tidak punya vendor **dan** tidak
punya nomor nota. Salah satu terisi sudah cukup — nomor nota marketplace
menjawab pertanyaan yang sama.

Halaman Pemeriksaan Data adalah tempat yang tepat: ia mengumpulkan hal-hal yang
belum tentu salah tetapi layak dilihat, tanpa menghalangi pencatatan.

## Aturan yang tertulis dua kali

Aturan siapa boleh menyetujui pesanan sekarang ada di dua tempat:

| Tempat | Dipakai untuk |
|---|---|
| `PurchaseOrderService::pastikanBolehMenyetujui()` | Menolak tindakan |
| `PurchaseOrder::bolehDisetujuiOleh()` | Menampilkan tombol |

Keduanya membaca konfigurasi yang sama dan sekarang sepakat. Yang tidak
dijamin: keduanya tetap sepakat setelah aturannya berubah. Kalau hanya satu
yang diperbarui, gejalanya berupa tombol yang muncul lalu ditolak — atau lebih
buruk, tombol yang tidak muncul untuk orang yang sebenarnya berwenang.

### Usulan

Jadikan model satu-satunya sumber, dan service memakainya:

```php
// PurchaseOrderService
protected function pastikanBolehMenyetujui(PurchaseOrder $po, ?User $pengguna): void
{
    if ($po->bolehDisetujuiOleh($pengguna)) {
        return;
    }

    throw new Exception($po->alasanTidakBolehDisetujui($pengguna));
}
```

Model menyimpan aturannya; `alasanTidakBolehDisetujui()` menjelaskan
penolakannya supaya pesan kesalahannya tetap sejelas sekarang. Satu aturan, dua
pemakai, tidak ada salinan.

## Batas kumulatif

Batas persetujuan mandiri berlaku per pesanan, jadi bisa ditembus dengan
memecah pesanan:

```
3 pesanan @Rp 4.900.000 disetujui sendiri → Rp 14.700.000 tanpa mata kedua
```

Ini lubang kebijakan, bukan bug. Aturan per pesanan tidak akan pernah
menutupnya, seberapa pun batasnya diturunkan.

### Usulan

Tambahkan batas kedua: **total persetujuan mandiri per pengaju per bulan
berjalan**.

```php
// config/pengadaan.php
'batas_persetujuan_mandiri' => 5_000_000,   // per pesanan
'batas_mandiri_per_bulan'   => 15_000_000,  // akumulasi per pengaju
```

Saat menyetujui sendiri, jumlahkan nilai pesanan yang bulan ini sudah disetujui
sendiri oleh orang yang sama. Kalau ditambah pesanan ini melewati batas bulanan,
persetujuan orang kedua kembali diwajibkan — pesan penolakannya menyebutkan
berapa yang sudah terpakai.

Angka Rp 15 juta itu tebakan. Yang menentukan besarnya adalah kewajaran belanja
IT Anda dalam sebulan, bukan kelipatan dari batas per pesanan.

### Kalau tidak dikerjakan

Boleh. Pemecahan pesanan meninggalkan jejak yang sangat jelas — tiga pesanan
berturut-turut tepat di bawah batas, semuanya disetujui sendiri, semuanya oleh
orang yang sama. Laporan sederhana atas pola itu bisa menggantikan penjagaannya,
dan tidak menghambat siapa pun yang bekerja jujur.

Pilih satu: penjagaan otomatis, atau kesengajaan yang mudah terlihat. Jangan
keduanya — dua lapis untuk risiko sebesar ini tidak sepadan.
