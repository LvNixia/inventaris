<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Aturan perpindahan status pesanan pembelian.
 *
 *   draft ──▶ pending_approval ──▶ approved ──▶ partial_receipt ──▶ completed
 *     │              │                 │
 *     └──────────────┴─────────────────┴──────────▶ cancelled
 *
 * `partial_receipt` dan `completed` tidak diatur di sini; keduanya ditetapkan
 * oleh penerimaan barang pada Tahap B.
 */
class PurchaseOrderService
{
    public function __construct(protected DocumentNumberGenerator $numberGenerator) {}

    public function recalculate(PurchaseOrder $po): PurchaseOrder
    {
        $po->loadMissing('items');

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($po->items as $item) {
            $nilaiBaris = (float) $item->unit_price * (int) $item->quantity;

            $subtotal += $nilaiBaris;
            $tax += $nilaiBaris * ((float) $item->tax_percent / 100);

            if ((float) $item->total_price !== $nilaiBaris) {
                $item->update(['total_price' => $nilaiBaris]);
            }
        }

        $po->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ]);

        return $po->refresh();
    }

    /**
     * Ajukan PO untuk disetujui.
     *
     * @throws Exception
     */
    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::withoutGlobalScopes()->where('id', $po->id)->lockForUpdate()->first();

            if ($po->status !== 'draft') {
                throw new Exception('Hanya PO berstatus draf yang bisa diajukan.');
            }

            if ($po->items()->count() === 0) {
                throw new Exception('PO belum berisi barang apa pun.');
            }

            foreach ($po->items as $item) {
                if ((int) $item->quantity < 1) {
                    throw new Exception("Jumlah pada barang {$item->product?->name} harus lebih dari 0.");
                }
            }

            $this->recalculate($po);

            $po->update([
                'status' => 'pending_approval',
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            return $po->refresh();
        });
    }

    /**
     * Setujui PO dan terbitkan nomornya.
     *
     * @throws Exception
     */
    public function approve(PurchaseOrder $po): PurchaseOrder
    {
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::withoutGlobalScopes()
                ->with('branch')
                ->where('id', $po->id)
                ->lockForUpdate()
                ->first();

            if ($po->status !== 'pending_approval') {
                throw new Exception('Hanya PO yang menunggu persetujuan yang bisa disetujui.');
            }

            $pengguna = auth()->user();

            $this->recalculate($po);

            $this->pastikanBolehMenyetujui($po->refresh(), $pengguna);

            $po->update([
                'status' => 'approved',
                'po_number' => $this->numberGenerator->generate($po->branch, $po->po_date, 'purchase_order'),
                'approved_by' => $pengguna->id,
                'approved_at' => now(),
            ]);

            return $po->refresh();
        });
    }

    /**
     * Kembalikan PO ke draf agar bisa diperbaiki.
     *
     * @throws Exception
     */
    public function returnToDraft(PurchaseOrder $po, string $alasan): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $alasan) {
            $po = PurchaseOrder::withoutGlobalScopes()->where('id', $po->id)->lockForUpdate()->first();

            if ($po->status !== 'pending_approval') {
                throw new Exception('Hanya PO yang menunggu persetujuan yang bisa dikembalikan.');
            }

            if (trim($alasan) === '') {
                throw new Exception('Alasan wajib diisi.');
            }

            $po->update([
                'status' => 'draft',
                'submitted_by' => null,
                'submitted_at' => null,
                'notes' => trim($po->notes."\n[Dikembalikan] ".$alasan),
            ]);

            return $po->refresh();
        });
    }

    /**
     * Batalkan PO.
     *
     * @throws Exception
     */
    public function cancel(PurchaseOrder $po, string $alasan): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $alasan) {
            $po = PurchaseOrder::withoutGlobalScopes()->where('id', $po->id)->lockForUpdate()->first();

            if (trim($alasan) === '') {
                throw new Exception('Alasan wajib diisi.');
            }

            if ($po->status === 'cancelled') {
                throw new Exception('PO ini sudah dibatalkan.');
            }

            if ($po->status === 'completed') {
                throw new Exception('PO yang sudah selesai tidak bisa dibatalkan.');
            }

            // Barang yang sudah telanjur diterima tidak bisa ditarik kembali
            // hanya dengan membatalkan pesanannya.
            if ($po->status === 'partial_receipt') {
                throw new Exception('PO sudah punya penerimaan barang; batalkan penerimaannya terlebih dahulu.');
            }

            // Penerimaan berstatus draf pun menghalangi: bila dibiarkan, ia masih
            // bisa disetujui belakangan dan melahirkan unit atas pesanan yang
            // sudah tidak berlaku.
            $penerimaan = GoodsReceipt::withoutGlobalScopes()
                ->where('purchase_order_id', $po->id)
                ->where('status', '!=', 'cancelled')
                ->get();

            if ($penerimaan->isNotEmpty()) {
                throw new Exception(
                    'PO masih punya '.$penerimaan->count().' penerimaan barang yang belum dibatalkan: '
                    .$penerimaan->map(fn (GoodsReceipt $gr): string => $gr->gr_number ?? 'draf #'.$gr->id)->join(', ')
                    .'. Batalkan atau hapus penerimaannya terlebih dahulu.'
                );
            }

            $po->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $alasan,
            ]);

            return $po->refresh();
        });
    }

    /**
     * Aturan siapa yang boleh menyetujui sebuah pesanan.
     *
     * Pesanan bernilai kecil boleh disetujui pengajunya sendiri. Menahan
     * belanja seharga beberapa ratus ribu sampai atasan sempat membuka
     * aplikasi tidak menghasilkan kontrol apa pun — yang terjadi justru akun
     * penyetuju dipinjam, dan jejaknya hilang. Di atas batas itu pemeriksaan
     * orang kedua baru sepadan.
     *
     * Batasnya ada di config/pengadaan.php.
     *
     * @throws Exception
     */
    protected function pastikanBolehMenyetujui(PurchaseOrder $po, ?User $pengguna): void
    {
        $batas = (float) config('pengadaan.batas_persetujuan_mandiri', 0);

        // Peninjau tidak pernah menyetujui apa pun, seberapa kecil pun
        // nilainya. Batas nilai melonggarkan siapa di antara pengelola yang
        // boleh menyetujui, bukan membuka pintu bagi yang hanya boleh melihat.
        if (! in_array($pengguna?->role, [Role::AdminPusat, Role::AdminCabang], true)) {
            throw new Exception('Peran Anda tidak berwenang menyetujui pesanan pembelian.');
        }

        if ((float) $po->total <= $batas) {
            return;
        }

        if ($pengguna?->role !== Role::AdminPusat) {
            throw new Exception(sprintf(
                'Pesanan di atas Rp %s hanya bisa disetujui Admin Pusat.',
                number_format($batas, 0, ',', '.'),
            ));
        }

        if ($po->submitted_by && (int) $po->submitted_by === (int) $pengguna->id) {
            throw new Exception(sprintf(
                'Pesanan di atas Rp %s harus disetujui orang lain, bukan pengajunya sendiri.',
                number_format($batas, 0, ',', '.'),
            ));
        }
    }

    /**
     * Hitung ulang nilai PO dari baris-barisnya.
     *
     * Dipanggil setiap kali baris berubah. Nilainya disimpan, bukan dihitung
     * saat tampil, supaya PO yang sudah disetujui tidak ikut bergeser ketika
     * harga barang diperbarui.
     */
}
