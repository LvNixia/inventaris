<?php

namespace Tests\Unit;

use App\Models\AssetStatus;
use App\Models\Condition;
use App\Models\DisposalReason;
use App\Services\HandoverService;
use App\Services\StockCalculator;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MembuatDataAset;
use Tests\TestCase;

/**
 * StockCalculator tidak lagi menghitung kuantitas apa pun. Tugasnya kini
 * menurunkan keadaan unit dari transaksi terakhirnya, jadi itulah yang diuji.
 */
class StockCalculatorTest extends TestCase
{
    use MembuatDataAset, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanDataAcuan();
    }

    public function test_unit_baru_belum_dipegang_siapa_pun(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();

        $this->assertNull($unit->current_holder_id);
        $this->assertNull($unit->retired_at);
        $this->assertTrue($unit->isAvailable());
    }

    public function test_status_dan_pemegang_mengikuti_transaksi_terakhir(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $penerima = $this->karyawan();

        app(HandoverService::class)->issue($this->draf($unit, $penerima));

        $this->assertSame('in_use', $unit->refresh()->currentStatus->code);
        $this->assertSame($penerima->id, $unit->current_holder_id);

        app(TransactionService::class)->return($unit, ['from_employee_id' => $penerima->id]);

        // Status spare menandai clears_holder, jadi pemegangnya ikut dikosongkan.
        $this->assertSame('spare', $unit->refresh()->currentStatus->code);
        $this->assertNull($unit->current_holder_id);
    }

    public function test_kondisi_ikut_berubah_saat_transaksi_menyebutkannya(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $rusakRingan = Condition::where('code', 'rusak_ringan')->value('id');

        app(TransactionService::class)->changeStatus($unit, [
            'status_id' => AssetStatus::where('code', 'broken')->value('id'),
            'condition_after_id' => $rusakRingan,
            'notes' => 'Engsel retak.',
        ]);

        $this->assertSame($rusakRingan, $unit->refresh()->condition_id);
        $this->assertFalse($unit->isAvailable());
    }

    public function test_pelepasan_menandai_tanggal_pensiun(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();

        app(TransactionService::class)->dispose($unit, [
            'transaction_date' => now()->subDays(3)->toDateString(),
            'disposal_reason_id' => DisposalReason::where('code', 'dibuang')->value('id'),
        ]);

        $unit->refresh();

        $this->assertNotNull($unit->retired_at);
        $this->assertSame(now()->subDays(3)->toDateString(), $unit->retired_at->toDateString());
        $this->assertFalse($unit->isAvailable());
    }

    public function test_unit_yang_sudah_dilepas_tidak_bisa_dilepas_lagi(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();
        $dibuang = DisposalReason::where('code', 'dibuang')->value('id');

        app(TransactionService::class)->dispose($unit, ['disposal_reason_id' => $dibuang]);

        $this->expectExceptionMessage('sudah dilepas');

        app(TransactionService::class)->dispose($unit->refresh(), ['disposal_reason_id' => $dibuang]);
    }

    public function test_penarikan_ditolak_bila_unit_tidak_dipegang(): void
    {
        $unit = $this->beli($this->barang(), 1)->first();

        $this->expectExceptionMessage('tidak sedang dipegang');

        app(TransactionService::class)->return($unit, ['from_employee_id' => $this->karyawan()->id]);
    }

    public function test_penghitung_membedakan_unit_tersedia_dipegang_dan_dilepas(): void
    {
        $barang = $this->barang();
        $units = $this->beli($barang, 3);
        $penerima = $this->karyawan();

        app(HandoverService::class)->issue($this->draf($units[0], $penerima));
        app(TransactionService::class)->dispose($units[1], [
            'disposal_reason_id' => DisposalReason::where('code', 'dibuang')->value('id'),
        ]);

        $this->assertSame(1, $barang->assets()->held()->count());
        $this->assertSame(1, $barang->assets()->retired()->count());
        $this->assertSame(1, $barang->assets()->available()->count());
        $this->assertSame(2, $barang->assets()->active()->count());

        // Cek utilitas pada service pun sejalan dengan scope di model.
        $this->assertTrue(app(StockCalculator::class)->isAvailable($units[2]->refresh()));
    }
}
