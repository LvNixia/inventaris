# 01 — Arsitektur & Stack

## 1. Teknologi

| Komponen | Pilihan | Alasan |
|---|---|---|
| Framework | Laravel 13 (PHP 8.3–8.5; dipakai PHP 8.5) | Fitur relevan: atribut `#[Authorize]`/`#[Middleware]`, `PreventRequestForgery` |
| Database | MySQL 8 / MariaDB 10.6+ | Transaksi & row lock dibutuhkan untuk stok dan counter |
| UI | **Opsi A (disarankan): Filament v5 Panel Builder** (Livewire 4 + Alpine + Tailwind sudah terkompilasi di paket) | Tidak butuh Node untuk panel standar; CRUD, tabel berfilter, Repeater, auth, widget tersedia. Node hanya untuk tema kustom — hindari |
| | Opsi B: Livewire 4 + Blade manual | Livewire menyajikan JS-nya sendiri. CSS lewat Tailwind Standalone CLI atau CSS precompiled |
| PDF | `barryvdh/laravel-dompdf` | Murni PHP. Browsershot tidak cocok (butuh Node) |
| Auth & peran | Auth panel Filament (A) atau Fortify + Blade (B); peran = enum `role` di `users` | **Jangan pakai starter kit resmi Laravel 13** — semuanya memakai Vite |
| Audit | `spatie/laravel-activitylog` | Nilai lama/baru tiap perubahan |
| Import/Export | `maatwebsite/excel` atau `openspout/openspout` | Murni PHP |

Tingkat keyakinan: [High confidence] Laravel 13 memerlukan PHP ≥ 8.3 dan mendukung 8.5. [Medium confidence] Filament v5 berjalan di Laravel 13 tanpa npm untuk panel standar; paket DomPDF/activitylog/excel sudah mendukung Laravel 13 — **verifikasi di tahap 0** dengan `composer require` di mesin tanpa Node.

## 2. Konsekuensi tanpa Node.js

| Yang hilang | Pengganti |
|---|---|
| Vite / `npm run build` | Tidak ada build step; aset dari paket atau file statis di `public/` |
| Starter kit auth + layout | Filament auth panel, atau Fortify + Blade sendiri |
| Tailwind via PostCSS | Tailwind Standalone CLI → `public/css/app.css` di-commit. Jangan pakai Play CDN di produksi |
| Alpine via npm | Dibundel di Livewire 4; tanpa Livewire, `alpine.min.js` statis |
| Browsershot | DomPDF |
| Chart.js | Filament Widgets (sudah dibundel) atau `chart.umd.js` statis |

Aturan praktis Filament: jangan pernah menjalankan `filament:make-theme`. Kustomisasi lewat warna panel, `renderHook`, dan CSS statis via `FilamentAsset::register()`.

## 3. Struktur direktori

```
app/
  Models/         Branch, Division, Position, Employee, Category, Brand, Condition, Vendor,
                  DisposalReason, ServiceKind, ServiceResult, AttachmentType,
                  AssetStatus, Asset, AssetAttachment, AssetTransaction,
                  HandoverDocument, HandoverItem, DocumentCounter, ImportBatch, AssetService, User
  Models/Scopes/  BranchScope
  Enums/          Role, StockDirection, TransactionType, DocumentStatus, ImportStatus
  Services/
    AssetCodeGenerator      -> IDS-LAP-004
    DocumentNumberGenerator -> IDS-IT/JKT/2026/09/01
    StockCalculator         -> qty_out/in/writeoff/available, pemegang & status saat ini
    HandoverService         -> draft, terbitkan (surat + transaksi, atomic)
    HandoverCancellation    -> pembatalan surat
    TransactionService      -> transaksi manual (kembali, servis, rusak, dilepas, koreksi)
    BranchTransferService   -> pindah cabang & mutasi karyawan
    EmployeeOffboarding     -> tarik semua aset karyawan
    ServiceService          -> buka/selesaikan servis & upgrade, perubahan spesifikasi
    MasterService           -> CRUD/nonaktif/gabung master lewat MasterRegistry
    DataCheckService        -> daftar anomali data
    PdfRenderer             -> render Blade -> PDF ke disk privat
    Import/                 AssetImporter, TransactionImporter, MasterImporter
    Export/                 AssetExport, TransactionExport, HolderBalanceExport, WorkbookBackup
  Policies/                 batasan cabang & peran
  Filament/                 (Opsi A)
    Resources/              Branch, Division, Position, Employee, Category, Brand, Condition, AssetStatus,
                            Vendor, DisposalReason, ServiceKind, ServiceResult, AttachmentType, User,
                            Asset (+ RelationManagers: Transactions, HandoverItems, Attachments),
                            AssetTransaction, HandoverDocument, ImportBatch, AssetService
    Pages/                  DataCheck, Help, ImportWizard, OpenServices
    Widgets/                StatsOverview, PerCategoryChart, PerStatusChart, PerBranchChart
  Http/Controllers/         HandoverPdfController, AttachmentController
  Console/Commands/         stock:recompute, backup:workbook, drafts:expire
database/migrations/, database/seeders/MasterSeeder.php
resources/views/pdf/handover.blade.php, return-receipt.blade.php, service-receipt.blade.php
lang/id/*.php
tests/Feature/, tests/Unit/, tests/Fixtures/
```

Semua Service menerima DTO/array sederhana dan mengembalikan Model; tidak ada Service yang mengimpor kelas Filament/Livewire.

## 4. Multi-cabang

- `assets`, `employees`, `handover_documents`, `users` punya `branch_id`.
- `BranchScope` (global scope) memfilter otomatis berdasarkan user login; admin pusat dibypass. Dipasang di Model supaya export, import, dan API ikut terfilter.
- `assets.branch_id` hanya berubah lewat transaksi pindah cabang ([alur/08-pindah-cabang.md](alur/08-pindah-cabang.md)).
- Kode cabang 3 huruf dipakai di nomor surat (usulan kode di [09-keputusan-terbuka.md](09-keputusan-terbuka.md)).
