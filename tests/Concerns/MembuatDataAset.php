<?php

namespace Tests\Concerns;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Employee;
use App\Models\HandoverDocument;
use App\Models\Product;
use App\Models\User;
use App\Services\AssetService;
use Database\Seeders\MasterSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Pembuat data secukupnya untuk menguji alur aset.
 *
 * Skema ini punya banyak foreign key ke data acuan, jadi menyiapkannya lewat
 * MasterSeeder jauh lebih murah daripada membangun factory untuk tiap tabel.
 */
trait MembuatDataAset
{
    protected function siapkanDataAcuan(): User
    {
        $this->seed(MasterSeeder::class);

        $admin = new User;
        $admin->name = 'Admin Uji';
        $admin->email = 'uji@contoh.test';
        $admin->password = 'rahasia-uji';
        $admin->role = 'admin_pusat';
        $admin->is_active = true;
        $admin->save();

        Auth::login($admin);

        return $admin;
    }

    protected function cabang(string $kode = 'JKT'): Branch
    {
        return Branch::where('code', $kode)->firstOrFail();
    }

    protected function barang(string $kategoriPrefix = 'ACC', string $model = 'MK270'): Product
    {
        return Product::create([
            'category_id' => Category::where('code_prefix', $kategoriPrefix)->value('id'),
            'brand_id' => Brand::firstOrCreate(['name' => 'Logitech'], ['is_active' => true])->id,
            'model' => $model,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $units
     * @return Collection<int, Asset>
     */
    protected function beli(Product $product, int $jumlah = 1, ?array $units = null, string $kodeCabang = 'JKT'): Collection
    {
        return app(AssetService::class)->receivePurchase([
            'product_id' => $product->id,
            'branch_id' => $this->cabang($kodeCabang)->id,
            'invoice_number' => 'INV/UJI/'.uniqid(),
            'purchase_date' => now()->subMonth(),
            'unit_price' => 350000,
            'warranty_months' => 12,
        ], $units ?? array_fill(0, $jumlah, ['serial_number' => null]));
    }

    protected function karyawan(string $nama = 'Penerima Uji', string $kodeCabang = 'JKT'): Employee
    {
        return Employee::create([
            'name' => $nama,
            'nik' => 'UJI-'.uniqid(),
            'branch_id' => $this->cabang($kodeCabang)->id,
            'is_active' => true,
        ]);
    }

    protected function draf(Asset $unit, Employee $penerima, ?Employee $penyerah = null): HandoverDocument
    {
        $penyerah ??= $this->karyawan('Penyerah Uji');

        $doc = HandoverDocument::create([
            'document_date' => now(),
            'branch_id' => $penyerah->branch_id,
            'first_party_id' => $penyerah->id,
            'second_party_id' => $penerima->id,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $doc->items()->create([
            'asset_id' => $unit->id,
            'user_employee_id' => $penerima->id,
        ]);

        return $doc;
    }
}
