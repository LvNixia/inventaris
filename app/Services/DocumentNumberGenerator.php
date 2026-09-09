<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DocumentCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    /**
     * Generate a new document number for a given branch and date.
     * Must be called within a database transaction or it will create its own.
     *
     * @param  string|Carbon  $date
     *
     * @throws \Exception
     */
    public function generate(Branch $branch, $date): string
    {
        $date = Carbon::parse($date);
        $year = $date->year;
        $month = $date->month;

        return DB::transaction(function () use ($branch, $year, $month) {
            // Get or create counter record and lock it
            $counter = DocumentCounter::firstOrCreate(
                ['branch_id' => $branch->id, 'year' => $year, 'month' => $month],
                ['last_no' => 0]
            );

            // Lock for update to prevent race conditions
            $counter = DocumentCounter::where('id', $counter->id)->lockForUpdate()->first();

            $counter->last_no += 1;
            $counter->save();

            $monthStr = str_pad($month, 2, '0', STR_PAD_LEFT);
            $seqStr = str_pad($counter->last_no, 2, '0', STR_PAD_LEFT);

            // Format: IDS-IT/{branch.code}/{YYYY}/{MM}/{NN}
            return "IDS-IT/{$branch->code}/{$year}/{$monthStr}/{$seqStr}";
        });
    }
}
