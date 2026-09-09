<?php

namespace Database\Seeders;

use App\Models\AssetStatus;
use App\Models\AttachmentType;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Condition;
use App\Models\DisposalReason;
use App\Models\PaymentTerm;
use App\Models\ServiceKind;
use App\Models\ServiceResult;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'in_use', 'name' => 'Dipakai', 'stock_direction' => 'out', 'transferable' => true, 'requires_qty' => true, 'clears_holder' => false],
            ['code' => 'spare', 'name' => 'Spare / Gudang', 'stock_direction' => 'in', 'transferable' => true, 'requires_qty' => true, 'clears_holder' => true],
            ['code' => 'service', 'name' => 'Servis', 'stock_direction' => 'neutral', 'transferable' => false, 'requires_qty' => false, 'clears_holder' => false],
            ['code' => 'broken', 'name' => 'Rusak', 'stock_direction' => 'neutral', 'transferable' => false, 'requires_qty' => false, 'clears_holder' => false],
            ['code' => 'disposed', 'name' => 'Dilepas / Dijual', 'stock_direction' => 'writeoff', 'transferable' => false, 'requires_qty' => true, 'clears_holder' => true],
            ['code' => 'registered', 'name' => 'Baru Didaftarkan', 'stock_direction' => 'neutral', 'transferable' => true, 'requires_qty' => false, 'clears_holder' => false],
            ['code' => 'in_transit', 'name' => 'Dalam Perjalanan', 'stock_direction' => 'neutral', 'transferable' => false, 'requires_qty' => false, 'clears_holder' => true],
        ];

        foreach ($statuses as $idx => $status) {
            AssetStatus::updateOrCreate(['code' => $status['code']], array_merge($status, ['is_system' => true, 'sort_order' => $idx + 1]));
        }

        $categories = [
            ['name' => 'Laptop', 'code_prefix' => 'LAP', 'requires_serial' => true],
            ['name' => 'Smartphone', 'code_prefix' => 'SMP', 'requires_serial' => true],
            ['name' => 'Tablet', 'code_prefix' => 'TAB', 'requires_serial' => true],
            ['name' => 'Desktop PC', 'code_prefix' => 'PC', 'requires_serial' => true],
            ['name' => 'Monitor', 'code_prefix' => 'MON', 'requires_serial' => true],
            ['name' => 'Printer', 'code_prefix' => 'PRN', 'requires_serial' => true],
            ['name' => 'Lisensi', 'code_prefix' => 'LSS', 'requires_serial' => true],
            ['name' => 'Aksesoris', 'code_prefix' => 'ACC', 'requires_serial' => false],
            ['name' => 'Kabel & Charger', 'code_prefix' => 'CBL', 'requires_serial' => false],
            ['name' => 'Tas & Casing', 'code_prefix' => 'BAG', 'requires_serial' => false],
        ];

        foreach ($categories as $idx => $cat) {
            Category::updateOrCreate(['code_prefix' => $cat['code_prefix']], array_merge($cat, ['sort_order' => $idx + 1]));
        }

        $conditions = [
            ['code' => 'baik', 'name' => 'Baik', 'applies_to' => 'all'],
            ['code' => 'hilang', 'name' => 'Hilang', 'applies_to' => 'all'],
            ['code' => 'rusak_ringan', 'name' => 'Rusak Ringan', 'applies_to' => 'hardware', 'is_system' => false],
            ['code' => 'rusak_berat', 'name' => 'Rusak Berat', 'applies_to' => 'hardware', 'is_system' => false],
        ];

        foreach ($conditions as $idx => $cond) {
            Condition::updateOrCreate(['code' => $cond['code']], array_merge($cond, ['is_system' => $cond['is_system'] ?? true, 'sort_order' => $idx + 1]));
        }

        $disposals = [
            ['code' => 'dijual', 'name' => 'Dijual', 'is_lost' => false],
            ['code' => 'dihibahkan', 'name' => 'Dihibahkan', 'is_lost' => false],
            ['code' => 'dibuang', 'name' => 'Dibuang', 'is_lost' => false],
            ['code' => 'hilang', 'name' => 'Hilang', 'is_lost' => true],
            ['code' => 'lainnya', 'name' => 'Lainnya', 'is_lost' => false],
        ];

        foreach ($disposals as $idx => $disp) {
            DisposalReason::updateOrCreate(['code' => $disp['code']], array_merge($disp, ['is_system' => true, 'sort_order' => $idx + 1]));
        }

        $services = [
            ['code' => 'perbaikan', 'name' => 'Perbaikan', 'changes_spec' => false],
            ['code' => 'upgrade', 'name' => 'Upgrade', 'changes_spec' => true],
            ['code' => 'perawatan', 'name' => 'Perawatan', 'changes_spec' => false],
        ];

        foreach ($services as $idx => $serv) {
            ServiceKind::updateOrCreate(['code' => $serv['code']], array_merge($serv, ['is_system' => true, 'sort_order' => $idx + 1]));
        }

        $results = [
            ['code' => 'diperbaiki', 'name' => 'Diperbaiki', 'applies_spec' => false, 'marks_broken' => false],
            ['code' => 'ganti_part', 'name' => 'Ganti Part', 'applies_spec' => true, 'marks_broken' => false],
            ['code' => 'tidak_bisa_diperbaiki', 'name' => 'Tidak bisa diperbaiki', 'applies_spec' => false, 'marks_broken' => true],
            ['code' => 'dibatalkan', 'name' => 'Dibatalkan', 'applies_spec' => false, 'marks_broken' => false],
        ];

        foreach ($results as $idx => $res) {
            ServiceResult::updateOrCreate(['code' => $res['code']], array_merge($res, ['is_system' => true, 'sort_order' => $idx + 1]));
        }

        $attachments = [
            ['code' => 'photo', 'name' => 'Foto'],
            ['code' => 'invoice', 'name' => 'Faktur / Kwitansi'],
            ['code' => 'warranty', 'name' => 'Kartu Garansi'],
            ['code' => 'service_receipt', 'name' => 'Tanda Terima Servis'],
            ['code' => 'other', 'name' => 'Lainnya'],
        ];

        foreach ($attachments as $att) {
            AttachmentType::updateOrCreate(['code' => $att['code']], array_merge($att, ['is_system' => true]));
        }

        // Syarat pembayaran vendor; `days` menentukan jarak jatuh tempo faktur.
        $terms = [
            ['code' => 'cod', 'name' => 'Bayar di Tempat', 'days' => 0],
            ['code' => 'cbd', 'name' => 'Bayar di Muka', 'days' => 0],
            ['code' => 'net7', 'name' => 'Net 7 Hari', 'days' => 7],
            ['code' => 'net14', 'name' => 'Net 14 Hari', 'days' => 14],
            ['code' => 'net30', 'name' => 'Net 30 Hari', 'days' => 30],
            ['code' => 'net45', 'name' => 'Net 45 Hari', 'days' => 45],
        ];

        foreach ($terms as $idx => $term) {
            PaymentTerm::updateOrCreate(['code' => $term['code']], array_merge($term, [
                'is_system' => true,
                'sort_order' => $idx + 1,
            ]));
        }

        // Data awal untuk testing/development
        Branch::firstOrCreate(['code' => 'JKT'], ['name' => 'Jakarta (Pusat)']);
        Branch::firstOrCreate(['code' => 'BTM'], ['name' => 'Batam']);
    }
}
