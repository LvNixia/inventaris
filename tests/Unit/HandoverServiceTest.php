<?php

namespace Tests\Unit;

use App\Models\AssetTransaction;
use App\Services\HandoverCancellation;
use App\Services\HandoverService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * Penerbitan dan pembatalan surat serah terima.
 */
class HandoverServiceTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanDataAcuan();
    }

    public function test_penerbitan_memberi_nomor_dan_memindahkan_unit(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $penerima = $this->karyawan();

        $doc = app(HandoverService::class)->issue($this->draf($unit, $penerima));

        $this->assertSame('issued', $doc->status);
        $this->assertNotNull($doc->document_number);
        $this->assertSame($penerima->id, $unit->refresh()->current_holder_id);
        $this->assertFalse($unit->isAvailable());
    }

    public function test_penerbitan_membekukan_rincian_barang_pada_barisnya(): void
    {
        $unit = $this->beli($this->barang('LAP', 'ThinkPad T14'), 1, [
            ['serial_number' => 'PF-UJI-0009'],
        ])->first();

        $doc = app(HandoverService::class)->issue($this->draf($unit, $this->karyawan()));
        $item = $doc->items()->first();

        // Rincian dibekukan agar surat lama tetap terbaca apa adanya walau
        // data barangnya kelak diubah.
        $this->assertSame('Logitech ThinkPad T14', $item->item_name);
        $this->assertSame('PF-UJI-0009', $item->serial_number);
    }

    public function test_surat_yang_sudah_terbit_tidak_bisa_diterbitkan_ulang(): void
    {
        $doc = app(HandoverService::class)->issue(
            $this->draf($this->beli($this->barang(), 1)->first(), $this->karyawan())
        );

        $this->expectExceptionMessage('Hanya dokumen draft');

        app(HandoverService::class)->issue($doc);
    }

    public function test_pembatalan_mengembalikan_unit_ke_gudang(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $doc = app(HandoverService::class)->issue($this->draf($unit, $this->karyawan()));

        app(HandoverCancellation::class)->cancel($doc, 'Salah penerima.');

        $this->assertSame('cancelled', $doc->refresh()->status);
        $this->assertNull($unit->refresh()->current_holder_id);
        $this->assertTrue($unit->isAvailable());
        $this->assertDatabaseHas('asset_transactions', [
            'asset_id' => $unit->id,
            'type' => 'cancellation',
            'stock_direction' => 'in',
        ]);
    }

    public function test_pembatalan_ditolak_bila_unit_sudah_bergerak_lagi(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $penerima = $this->karyawan();
        $doc = app(HandoverService::class)->issue($this->draf($unit, $penerima));

        // Unit ditarik lebih dulu; riwayat setelah surat tidak bisa dibalik.
        app(TransactionService::class)->return($unit->refresh(), [
            'from_employee_id' => $penerima->id,
        ]);

        $this->expectExceptionMessage('sudah bergerak setelah surat ini');

        app(HandoverCancellation::class)->cancel($doc->refresh(), 'Salah penerima.');
    }

    public function test_pembatalan_wajib_menyertakan_alasan(): void
    {
        $doc = app(HandoverService::class)->issue(
            $this->draf($this->beli($this->barang(), 1)->first(), $this->karyawan())
        );

        $this->expectExceptionMessage('Alasan wajib diisi');

        app(HandoverCancellation::class)->cancel($doc, '   ');
    }

    /**
     * Menyerahkan ke karyawan cabang lain ikut memindahkan unitnya, jadi
     * pembatalannya harus mengembalikan unit ke cabang asal.
     */
    public function test_pembatalan_mengembalikan_unit_ke_cabang_asal(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $cabangAsal = $unit->branch_id;
        $penerima = $this->karyawan('Penerima Batam', 'BTM');

        $doc = app(HandoverService::class)->issue($this->draf($unit, $penerima));

        $this->assertSame($this->cabang('BTM')->id, $unit->refresh()->branch_id);

        app(HandoverCancellation::class)->cancel($doc, 'Batal, penerima pindah.');

        $this->assertSame($cabangAsal, $unit->refresh()->branch_id);
        $this->assertSame(2, AssetTransaction::where('asset_id', $unit->id)
            ->where('type', 'branch_transfer')
            ->count());
    }
}
