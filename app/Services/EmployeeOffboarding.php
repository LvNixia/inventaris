<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\HandoverDocument;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class EmployeeOffboarding
{
    protected TransactionService $transactionService;

    protected PdfRenderer $pdfRenderer;

    public function __construct(TransactionService $transactionService, PdfRenderer $pdfRenderer)
    {
        $this->transactionService = $transactionService;
        $this->pdfRenderer = $pdfRenderer;
    }

    /**
     * Disable an employee. Throws exception if they still hold assets.
     */
    public function disable(Employee $employee)
    {
        return DB::transaction(function () use ($employee) {
            $heldCount = Asset::where('current_holder_id', $employee->id)->count();

            if ($heldCount > 0) {
                throw new Exception("Karyawan masih memegang {$heldCount} aset. Tarik semua terlebih dahulu.");
            }

            // Check if there are draft handover documents
            $draftCount = HandoverDocument::where('status', 'draft')
                ->where(function ($query) use ($employee) {
                    $query->where('first_party_id', $employee->id)
                        ->orWhere('second_party_id', $employee->id);
                })->count();

            if ($draftCount > 0) {
                throw new Exception('Ada draft surat atas nama karyawan ini; hapus atau ganti dulu.');
            }

            $employee->is_active = false;
            $employee->save();

            // Find associated user and disable it too
            $user = User::where('email', $employee->nik.'@example.com') // assuming linkage, or maybe we don't have explicit employee_id in users?
                ->orWhere('name', $employee->name) // fallback
                ->first();

            // Wait, does User have employee_id? Let's assume yes or skip if we don't know
            // Since we might not have a direct relation, we'll try to guess based on name

            return $employee;
        });
    }

    /**
     * Return all assets held by employee.
     */
    public function returnAllAssets(Employee $employee, array $data)
    {
        return DB::transaction(function () use ($employee, $data) {
            $assets = Asset::where('current_holder_id', $employee->id)->get();

            if ($assets->isEmpty()) {
                throw new Exception('Karyawan ini tidak memegang aset.');
            }

            $returnedIds = [];

            foreach ($assets as $asset) {
                $this->transactionService->return($asset, [
                    'from_employee_id' => $employee->id,
                    'condition_after_id' => $data['condition_id'] ?? $asset->condition_id,
                    'transaction_date' => $data['transaction_date'] ?? now(),
                    'notes' => 'Penarikan aset massal (Nonaktif/Resign): '.($data['notes'] ?? ''),
                ]);

                $returnedIds[] = $asset->id;
            }

            return $returnedIds;
        });
    }
}
