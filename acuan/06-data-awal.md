# 06 — Data Awal

## 1. Urutan pemasukan data
1. **Seeder master** (`MasterSeeder`, idempoten, `updateOrCreate` berdasarkan nama) dari §2 dan seed di [02-model-data.md](02-model-data.md).
2. **Lengkapi karyawan** yang muncul sebagai pemakai: Irpan, Egi, Fazar, Husna (= Annisa Alhusna?), Lala, Dani, Rizky, Riyan, Salsa, Tegar, Kirana, Sheik, Pelis (sudah keluar) — jabatan, divisi, cabang; konfirmasi manual.
3. **Bersihkan data** sesuai catatan §3.
4. **Import aset** §3 lewat [alur/12-import.md](alur/12-import.md) mode `--keep-codes` (kode sudah tercetak di surat fisik), lalu set `categories.last_seq` = nomor terbesar per prefix.
5. **Import transaksi** §4 urut baris, `type = legacy`, `legacy = true`; `keterangan` berpola `Dipakai oleh X` dipetakan ke `user_employee_id` lewat tabel panggilan → karyawan; yang tidak cocok tetap di `notes`.
6. **Buat `handover_documents`** dari 5 nomor surat unik di §4 (`issued`, `legacy`, tanpa PDF); tautkan transaksinya.
7. **Verifikasi** = skenario uji 17 terhadap §5.

## 2. Master awal

**Cabang (13):** Cabang Jakarta, Cabang Batam, Cabang Medan, Cabang Palembang, Cabang Cikarang, Cabang Semarang, Cabang Surabaya, Cabang Samarinda, Cabang Balikpapan, Cabang Banjarmasin, Cabang Manado, Cabang Makassar, Cabang Kendari.

**Divisi (13):** Information Technology, Technical support, Business Development, Sales, Marketing, Finance & Accounting, HRD & GA, Warehouse / Logistik, Service & Calibration, Rental, Teknisi, Geomarketing, Digital Marketing.

**Merk (17):** Lenovo, Asus, Dell, HP, Acer, Apple, Samsung, Xiaomi, Oppo, Infinix, Logitech, Epson, Zyrex, Microsoft, Axioo, Robot, Motorola.

**Kondisi (7):** Baik (`code baik`, sistem), Perlu Servis, Rusak Ringan, Rusak Berat (`hardware`); Aktif, Mati (`license`); Hilang (`code hilang`, sistem, `all`).

**Jabatan (10):** dari kolom jabatan karyawan di bawah (Staff Information Technology, Head Technical support, Manager Business Development, Head of Jakarta Branch, Staff Teknisi, Head Finance & Accounting, Manager Support & Marketing, Staff Geomarketing, KaDiv Digital Marketing, KaDiv Teknisi).

**Vendor (3):** Tokopedia, Shopee, CD Key Offer (`toko`); kolom `vendor` di data aset dipetakan ke sini; baris tanpa vendor → null.

**Alasan dilepas, jenis servis, hasil servis, jenis lampiran:** seed bawaan sesuai [11-master-data.md](11-master-data.md).

**Karyawan (10)** — semua Cabang Jakarta (asumsi):

| name | position | division | witness |
|---|---|---|---|
| Akbar Ramadhan | Staff Information Technology | Information Technology | tidak |
| Arief Budiman | Head Technical support | Technical support | tidak |
| Cholid Ridwan | Manager Business Development | Business Development | **ya** |
| Aris Munandar | Head of Jakarta Branch | Sales | tidak |
| Karina | Staff Teknisi | Teknisi | tidak |
| Zepa | Head Finance & Accounting | Finance & Accounting | tidak |
| Sandy Yudistira Mahardika | Manager Support & Marketing | Technical support | tidak |
| Annisa Alhusna | Staff Geomarketing | Geomarketing | tidak |
| Muhammad Rafdi Habiburahman | KaDiv Digital Marketing | Digital Marketing | tidak |
| Yadi Maulana Rosid | KaDiv Teknisi | Teknisi (dilengkapi) | tidak |

## 3. Aset (24) — header = template import "Aset baru"

```csv
kode_aset,kategori,merk,tipe_model,jumlah,nomor_seri,imei_1,imei_2,spesifikasi,aksesoris,kondisi,tanggal_beli,vendor,no_invoice,harga_satuan,garansi_sampai,lokasi,catatan
IDS-LAP-001,Laptop,Zyrex,D-tech Pro V2,1,DTCP1025BA0457,,,AMD Ryzen 5 6600H;16GB RAM DDR5 4800 MHz;SSD NVMe 512GB,1x Charger,Baik,2026-07-24,Tokopedia,585181957627217000,7787290,2028-07-23,Cabang Jakarta,Untuk sales Irpan
IDS-LSS-001,Lisensi,Microsoft,OFFICE OHS 2019 PROFESSIONAL ORIGINAL RETAIL PACK,1,VVTXY-CHN8R-3JYVW-CMB92-P9Y86,,,,1X Kartu,Aktif,2026-07-24,Shopee,260724GWJK0MRQ,777500,,Cabang Jakarta,IDS-LAP-003 | Zyrex D-tech Pro V2 | DTCP1025BA0457
IDS-LAP-002,Laptop,Axioo,HYPE 5 AMD X5-2,1,0225290070577050341,,,AMD Ryzen 5 7430U;16GB RAM DDR4 3200 MHz; SSD 256GB,1x charger,Aktif,2025-11-18,Tokopedia,581248975892219836,6292260,2027-11-17,Cabang Jakarta,Untuk sales Aris
IDS-LAP-003,Laptop,Zyrex,D-tech Pro V2,1,DTCP1025BA1780,,,AMD Ryzen 5 6600H;16GB RAM DDR5 4800 MHz;SSD NVMe 512GB,1x charger,Baik,2026-07-31,Tokopedia,585295842226898418,7636708,2028-07-30,Cabang Jakarta,Untuk sales Egi
IDS-LSS-002,Lisensi,Microsoft,MS Office2024 Professional Plus CD Key Global,1,MYQNY-GXBXW-6CPMD-D332C-YKRPQ,,,,1x kartu,Aktif,2026-07-31,CD Key Offer,CO202607311906061762,416895,,Cabang Jakarta,IDS-LAP-003 | Zyrex D-tech Pro V2 | DTCP1025BA1780
IDS-LAP-004,Laptop,Lenovo,V15-IIL,1,PF2NQCMWMTM,,,Intel Core I3 1005G1;8GB RAM DDR4 2667 MHz;SSD 256GB,1x Charger,Baik,2021-09-01,,,6750000,2023-08-31,Cabang Jakarta,Untuk staff IT
IDS-LAP-005,Laptop,Lenovo,V14 Gen 4,1,PF56QHAS,,,AMD Ryzen 5 7430U; 16GB RAM DDR4 3200 MHz;SSD 512GB,1x Charger,Baik,2023-04-26,Tokopedia,,7300000,2025-04-25,Cabang Jakarta,Untuk staff IT
IDS-LAP-006,Laptop,Acer,Aspire A514-54,1,14904271234,,,Intel Core i3-1115G4;12GB RAM DDR4 2667 MHz;SSD 512GB,1x Charger,Baik,2020-12-23,,,5699000,2022-12-22,Cabang Jakarta,Untuk Staff Geomartketing
IDS-LAP-007,Laptop,HP,HP 240 G6,1,5CD8382P46,,,Intel Core i5-7200U;8GB RAM DDR4 2133MHz;HDD 1TB,1x Charger,Baik,2017-11-23,,,4500000,2019-11-22,Cabang Jakarta,Untuk Staff Keuangan
IDS-LAP-008,Laptop,Lenovo,IdeaPad 3-14IIL05,1,PF24CCS0,,,Intel Core i3-1005G1;8GB RAM DDR4 2400MHz;SSD 512GB,1x Charger,Baik,2020-07-08,,,6300000,2022-07-07,Cabang Jakarta,Ex teh Pelis keuangan
IDS-LAP-009,Laptop,Acer,Aspire A514-53,1,101021580223,,,Intel Core I3 1005G1;8GB RAM DDR4 2667 MHz;SSD 512GB,1x Charger,Baik,2020-08-20,,,6999000,2022-08-19,Cabang Jakarta,Untuk staff keuangan
IDS-LAP-010,Laptop,Asus,TUF Gaming A15 FA506NCG,1,TBNRCX02842747E,,,AMD Ryzen 7 7445HS w/ Radeon 740M Graphics;4GB VRAM NVDIA RTX 3050;16GB RAM DDR5 5600MHz;512GB SSD,1x Charger,Baik,2026-08-05,Shopee,260805HUKYK4VJ,15136000,2028-08-04,Cabang Jakarta,Untuk Staff Support
IDS-LAP-011,Laptop,Asus,TUF Gaming A15 FA506NCG,1,TBNRCX02878147A,,,AMD Ryzen 7 7445HS w/ Radeon 740M Graphics;4GB VRAM NVDIA RTX 3050;16GB RAM DDR5 5600MHz;512GB SSD,1x Charger,Baik,2026-08-05,Shopee,260805HUKYK4VJ,15136000,2028-08-04,Cabang Jakarta,Untuk Manager Support Marketing
IDS-LAP-012,Laptop,Asus,Expertbook PM1403CDA,1,W2NXCV00D97406B,,,AMD Ryzen 7 170;16GB RAM DDR5 4790 MHz;SSD 512GB,1x Charger,Baik,2026-08-12,Tokopedia,585506363820967410,12387742,2028-08-11,Cabang Jakarta,Untuk Staff Geomarketing
IDS-LAP-013,Laptop,Lenovo,IdeaPad Slim 3 14IAU7,1,PF5AJFPQ,,,Intel Core i3-1215U;8GB RAM DDR4 3200 MHz;SSD 256GB,1x Charger,Baik,2022-06-17,Tokopedia,,6500000,2022-06-16,Cabang Jakarta,Untuk KaDiv Digital Marketing
IDS-LAP-014,Laptop,HP,Pavilion Gaming Laptop 15-ec2xxx,1,5CD151NJPZ,,,AMD Ryzen 5 5600H with Radeon Graphics;16GB RAM DDR4 3200 MHz; SSD 512GB,1x Charger,Baik,2021-09-22,Tokopedia,,14000000,2023-09-21,Cabang Jakarta,Untuk Staff Digital Marketing
IDS-LAP-015,Laptop,Lenovo,IdeaPad Gaming 3-15ACH6,1,MP25DF6S,,,AMD Ryzen 5 5600H with Radeon Graphics;16GB RAM DDR4 3200 MHz; SSD 512GB,1x Charger,Baik,2022-03-24,Tokopedia,,12000000,2024-03-23,Cabang Jakarta,Untuk Staff Digital Marketing
IDS-LAP-016,Laptop,Lenovo,V14 G3 IAP,1,PF50EA4W,,,Intel i3-1215U 1.2 GHz;8GB RAM DDR4 3200 MHz; SSD 256GB,1x Charger,Baik,2022-10-20,Tokopedia,,6100000,2024-10-19,Cabang Jakarta,Laptop aset Digital Marketing
IDS-LAP-017,Laptop,Lenovo,V14 G5 IRL,1,PF67J2QS,,,Intel i3-1315U;8GB RAM DDR5 5200 MHz;SSD 256GB,1x Charger,Baik,2026-06-10,Tokopedia,584457860289562098,8213400,2028-06-09,Cabang Jakarta,Laptop aset Digital Marketing
IDS-ACC-001,Aksesoris,Robot,M206 Wireless Mouse,5,,,,Wireless Mouse,,Baik,2026-08-26,Shopee,260826BYNCU0BS,48041,,Cabang Jakarta,Stok IT
IDS-LAP-018,Laptop,Lenovo,V14 G3 IAP,1,PF50RWNJ,,,Intel Core i3 1215U;8GB RAM DDR4 2666 MHz;SSD 256GB,1x Charger,Baik,2022-06-16,,,7000000,2024-06-15,Cabang Jakarta,Untuk staff Teknisi Pusat
IDS-LAP-019,Laptop,Lenovo,IdeaPad 3-14IIL05,1,PF34EXVN,,,Intel Core i3-10110U;12GB RAM DDR4 2666 MHz;SSD 256GB,1x Charger,Baik,2020-06-11,,,6300000,2022-06-10,Cabang Jakarta,Untuk staff Teknisi Pusat
IDS-SMP-001,Smartphone,Motorola,Moto G57 Power,1,PBAH0027ID,351516751406836,351516751406844,Snapdragon 6s Gen 4;8GB RAM 128GB Internal;7000 mAh Battery,1x Charger,Baik,2026-09-05,Shopee,260904665U7YUR,3232080,2027-09-04,Cabang Jakarta,Untuk Staff Support
IDS-SMP-002,Smartphone,Motorola,Moto G57 Power,1,,,,Snapdragon 6s Gen 4;8GB RAM 128GB Internal;7000 mAh Battery,1x Charger,Baik,,Shopee,,3232080,,Cabang Jakarta,Untuk Staff Support
```

Pembersihan sebelum import:
- `IDS-SMP-002`: tanpa serial, IMEI, tanggal beli → lengkapi.
- `IDS-LAP-013`: `garansi_sampai` sehari sebelum `tanggal_beli` → hampir pasti 2024-06-16.
- `IDS-LAP-002`: kondisi "Aktif" untuk laptop → "Baik".
- `IDS-LSS-001/002`: `catatan` berisi laptop tempat lisensi dipasang → biarkan sebagai catatan.

## 4. Riwayat (24, urut) — header + `pemakai` = template import "Transaksi"

```csv
tanggal,kode_aset,jumlah,dari,ke,status_setelah,no_surat,keterangan
2026-07-27,IDS-LAP-001,1,Akbar Ramadhan,Aris Munandar,Dipakai,IDS-IT/JKT/2026/07/09,Dipakai oleh irpan
2026-07-27,IDS-LSS-001,1,Akbar Ramadhan,Aris Munandar,Dipakai,IDS-IT/JKT/2026/07/09,Dipakai oleh irpan
2026-07-30,IDS-LAP-002,1,Akbar Ramadhan,Aris Munandar,Dipakai,,Dipakai oleh Aris
2026-07-31,IDS-LAP-003,1,Akbar Ramadhan,Aris Munandar,Dipakai,IDS-IT/JKT/2026/08/01,Dipakai oleh Egi
2026-07-31,IDS-LSS-002,1,Akbar Ramadhan,Aris Munandar,Dipakai,IDS-IT/JKT/2026/08/01,IDS-LAP-003 | Zyrex D-tech Pro V2 | DTCP1025BA1780
2026-08-03,IDS-LAP-004,1,Akbar Ramadhan,Akbar Ramadhan,Dipakai,,Dipakai oleh Fazar
2026-08-04,IDS-LAP-005,1,Akbar Ramadhan,Akbar Ramadhan,Dipakai,,Dipakai oleh Akbar
2026-08-04,IDS-LAP-006,1,Akbar Ramadhan,Akbar Ramadhan,Dipakai,,Dipakai oleh Husna
2026-08-05,IDS-LAP-007,1,Akbar Ramadhan,Zepa,Dipakai,,Dipakai oleh Lala
2026-08-05,IDS-LAP-008,1,Akbar Ramadhan,Zepa,Dipakai,,Aset keuangan (ex teh Pelis)
2026-08-05,IDS-LAP-009,1,Akbar Ramadhan,Zepa,Dipakai,,Dipakai oleh teh Zepa
2026-08-10,IDS-LAP-011,1,Akbar Ramadhan,Sandy Yudistira Mahardika,Dipakai,IDS-IT/JKT/2026/08/02,Dipakai oleh Sandy
2026-08-10,IDS-LAP-010,1,Akbar Ramadhan,Arief Budiman,Dipakai,IDS-IT/JKT/2026/08/03,Dipakai oleh Dani
2026-08-13,IDS-LAP-012,1,Akbar Ramadhan,Annisa Alhusna,Dipakai,IDS-IT/JKT/2026/08/04,Dipakai oleh Husna
2026-08-18,IDS-LAP-013,1,Akbar Ramadhan,Muhammad Rafdi Habiburahman,Dipakai,,Dipakai oleh Rafdi
2026-08-18,IDS-LAP-014,1,Akbar Ramadhan,Muhammad Rafdi Habiburahman,Dipakai,,Dipakai oleh Rizky
2026-08-18,IDS-LAP-015,1,Akbar Ramadhan,Muhammad Rafdi Habiburahman,Dipakai,,Dipakai oleh Riyan
2026-08-18,IDS-LAP-016,1,Akbar Ramadhan,Muhammad Rafdi Habiburahman,Dipakai,,Aset Digital Marketing
2026-08-18,IDS-LAP-017,1,Akbar Ramadhan,Muhammad Rafdi Habiburahman,Dipakai,,Dipakai oleh Salsa
2026-08-27,IDS-ACC-001,1,Akbar Ramadhan,Yadi Maulana Rosid,Dipakai,,Dipakai oleh Tegar
2026-08-28,IDS-ACC-001,1,Akbar Ramadhan,Sandy Yudistira Mahardika,Dipakai,,Dipakai oleh sandy
2026-09-01,IDS-LAP-018,1,Akbar Ramadhan,Yadi Maulana Rosid,Dipakai,,Dipakai oleh Tegar
2026-09-01,IDS-LAP-019,1,Akbar Ramadhan,Yadi Maulana Rosid,Dipakai,,Dipakai oleh Kirana
2026-09-07,IDS-SMP-001,1,Akbar Ramadhan,Arief Budiman,Dipakai,IDS-IT/JKT/2026/09/01,Dipakai oleh Sheik
```

## 5. Nilai yang diharapkan (fixture uji 17)

| kode | qty | qty_out | qty_available | pemegang | status |
|---|---|---|---|---|---|
| IDS-LAP-001, 002, 003, LSS-001, LSS-002 | 1 | 1 | 0 | Aris Munandar | Dipakai |
| IDS-LAP-004, 005, 006 | 1 | 1 | 0 | Akbar Ramadhan | Dipakai |
| IDS-LAP-007, 008, 009 | 1 | 1 | 0 | Zepa | Dipakai |
| IDS-LAP-010 | 1 | 1 | 0 | Arief Budiman | Dipakai |
| IDS-LAP-011 | 1 | 1 | 0 | Sandy Yudistira Mahardika | Dipakai |
| IDS-LAP-012 | 1 | 1 | 0 | Annisa Alhusna | Dipakai |
| IDS-LAP-013 … 017 | 1 | 1 | 0 | Muhammad Rafdi Habiburahman | Dipakai |
| IDS-ACC-001 | 5 | 2 | 3 | terakhir: Sandy; saldo Yadi 1, Sandy 1 | Dipakai |
| IDS-LAP-018, 019 | 1 | 1 | 0 | Yadi Maulana Rosid | Dipakai |
| IDS-SMP-001 | 1 | 1 | 0 | Arief Budiman | Dipakai |
| IDS-SMP-002 | 1 | 0 | 1 | — | Belum diserahkan |

Dashboard: 24 baris aset, 28 unit, 24 unit keluar, 4 unit tersedia (3 mouse + IDS-SMP-002), 1 baris belum pernah diserahkan, 0 baris bermasalah setelah pembersihan.
