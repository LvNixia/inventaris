<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Condition;
use App\Models\Product;
use App\Models\PurchaseBatch;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssetService
{
    protected AssetCodeGenerator $codeGenerator;

    protected StockCalculator $stockCalculator;

    public function __construct(AssetCodeGenerator $codeGenerator, StockCalculator $stockCalculator)
    {
        $this->codeGenerator = $codeGenerator;
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Daftarkan satu unit.
     */
    public function create(array $data): Asset
    {
        return DB::transaction(function () use ($data) {
            $product = Product::with('category')->findOrFail($data['product_id']);

            $data['asset_code'] = $this->codeGenerator->generate($product->category);
            $data['current_holder_id'] = null;
            $data['current_user_id'] = null;
            $data['current_status_id'] ??= AssetStatus::where('code', 'registered')->value('id');
            $data['condition_id'] ??= Condition::where('code', 'baik')->value('id');
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();

            return Asset::create($data);
        });
    }

    /**
     * Terima satu batch pembelian: buat batch-nya sekaligus unit-unitnya.
     *
     * Pengguna mengisi satu formulir seperti biasa. Untuk barang bernomor seri,
     * daftar serial menentukan banyaknya unit; untuk barang tanpa serial cukup
     * mengisi angka jumlah dan unitnya dibuat dengan serial kosong.
     *
     * @param  array<int, array{serial_number?: ?string, imei_1?: ?string, imei_2?: ?string}>  $units
     * @return Collection<int, Asset>
     *
     * @throws Exception
     */
    public function receivePurchase(array $batchData, array $units): Collection
    {
        return DB::transaction(function () use ($batchData, $units) {
            $product = Product::with('category')->findOrFail($batchData['product_id']);

            if (empty($units)) {
                throw new Exception('Isi minimal satu unit untuk pembelian ini.');
            }

            $batchData['warranty_until'] ??= $this->hitungAkhirGaransi(
                $batchData['purchase_date'] ?? null,
                $batchData['warranty_months'] ?? null,
            );
            $batchData['created_by'] = auth()->id();

            $batch = PurchaseBatch::create($batchData);

            $registered = AssetStatus::where('code', 'registered')->value('id');
            $baik = Condition::where('code', 'baik')->value('id');

            return collect($units)->map(fn (array $unit): Asset => $this->create([
                'product_id' => $product->id,
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'serial_number' => blank($unit['serial_number'] ?? null) ? null : $unit['serial_number'],
                'imei_1' => blank($unit['imei_1'] ?? null) ? null : $unit['imei_1'],
                'imei_2' => blank($unit['imei_2'] ?? null) ? null : $unit['imei_2'],
                'condition_id' => $unit['condition_id'] ?? $baik,
                'current_status_id' => $registered,
                'notes' => $unit['notes'] ?? null,
            ]));
        });
    }

    /**
     * Tambah unit ke batch pembelian yang sudah ada, misalnya saat sisa kiriman
     * baru datang belakangan.
     *
     * @param  array<int, array<string, mixed>>  $units
     * @return Collection<int, Asset>
     */
    public function addUnitsToBatch(PurchaseBatch $batch, array $units): Collection
    {
        return DB::transaction(fn (): Collection => collect($units)->map(fn (array $unit): Asset => $this->create([
            'product_id' => $batch->product_id,
            'purchase_batch_id' => $batch->id,
            'branch_id' => $batch->branch_id,
            'serial_number' => blank($unit['serial_number'] ?? null) ? null : $unit['serial_number'],
            'imei_1' => blank($unit['imei_1'] ?? null) ? null : $unit['imei_1'],
            'imei_2' => blank($unit['imei_2'] ?? null) ? null : $unit['imei_2'],
            'condition_id' => $unit['condition_id'] ?? Condition::where('code', 'baik')->value('id'),
            'current_status_id' => AssetStatus::where('code', 'registered')->value('id'),
            'notes' => $unit['notes'] ?? null,
        ])));
    }

    /**
     * Hapus unit. Hanya boleh bila belum punya riwayat sama sekali.
     *
     * @throws Exception
     */
    public function delete(Asset $asset): ?bool
    {
        if ($asset->assetTransactions()->exists() || $asset->handoverItems()->exists()) {
            throw new Exception('Aset tidak bisa dihapus karena sudah memiliki riwayat transaksi.');
        }

        if ($asset->assetServices()->exists()) {
            throw new Exception('Aset tidak bisa dihapus karena sudah memiliki catatan servis.');
        }

        return $asset->delete();
    }

    protected function hitungAkhirGaransi(mixed $tanggalBeli, ?int $bulan): ?string
    {
        if (blank($tanggalBeli) || blank($bulan) || $bulan < 1) {
            return null;
        }

        return Carbon::parse($tanggalBeli)->addMonths($bulan)->toDateString();
    }
}
