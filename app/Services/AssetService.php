<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class AssetService
{
    protected AssetCodeGenerator $codeGenerator;

    public function __construct(AssetCodeGenerator $codeGenerator)
    {
        $this->codeGenerator = $codeGenerator;
    }

    /**
     * Create a new asset with an auto-generated asset code.
     *
     * @param array $data
     * @return Asset
     * @throws \Exception
     */
    public function create(array $data): Asset
    {
        return DB::transaction(function () use ($data) {
            $category = Category::findOrFail($data['category_id']);
            
            // Generate code (locks category row)
            $assetCode = $this->codeGenerator->generate($category);
            
            $data['asset_code'] = $assetCode;
            
            // Initial quantities
            $data['qty_available'] = $data['quantity'] ?? 1;
            $data['qty_in'] = 0;
            $data['qty_out'] = 0;
            $data['qty_writeoff'] = 0;
            
            // Current status starts empty (Belum diserahkan)
            $data['current_status_id'] = null;
            $data['current_holder_id'] = null;
            
            return Asset::create($data);
        });
    }

    /**
     * Delete an asset. Allowed only if it has no transactions.
     *
     * @param Asset $asset
     * @return bool|null
     * @throws \Exception
     */
    public function delete(Asset $asset)
    {
        if ($asset->assetTransactions()->exists() || $asset->handoverItems()->exists()) {
            throw new \Exception("Aset tidak bisa dihapus karena sudah memiliki riwayat transaksi.");
        }

        return $asset->delete();
    }
}
