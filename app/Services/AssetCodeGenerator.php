<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class AssetCodeGenerator
{
    /**
     * Generate a new asset code for the given category.
     * Must be called within a database transaction or it will create its own.
     *
     * @throws \Exception
     */
    public function generate(Category $category): string
    {
        return DB::transaction(function () use ($category) {
            // Lock the category row to prevent race conditions
            $lockedCategory = Category::where('id', $category->id)->lockForUpdate()->first();

            if (! $lockedCategory) {
                throw new \Exception('Category not found.');
            }

            // Increment sequence
            $lockedCategory->last_seq += 1;
            $lockedCategory->save();

            // Format: IDS-{category.code_prefix}-{seq:3 digit}
            $seqPadded = str_pad($lockedCategory->last_seq, 3, '0', STR_PAD_LEFT);

            return "IDS-{$lockedCategory->code_prefix}-{$seqPadded}";
        });
    }
}
