<?php

namespace App\Services;

use App\Models\HandoverDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PdfRenderer
{
    /**
     * Render the handover document as a PDF and save it.
     * Returns the relative storage path.
     */
    public function handover(HandoverDocument $doc, bool $isDraft = false): string
    {
        $pdf = Pdf::loadView('pdf.handover', [
            'doc' => $doc,
            'isDraft' => $isDraft,
        ]);
        
        $pdf->setPaper('a4', 'portrait');

        if ($isDraft) {
            // For preview, we don't save it, just return the raw content? 
            // Wait, the caller might stream it or we return raw.
            // But we need a path if we follow the rule?
            // "Preview draft: PDF yang sama dengan tulisan DRAFT... tidak disimpan."
            // So if it's draft, we might just return the raw PDF binary or stream it.
            return $pdf->output(); 
        }

        $uuid = Str::uuid()->toString();
        $year = $doc->document_date->year;
        
        // Ensure directory exists
        $dir = "private/handovers/{$year}";
        if (!Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $path = "{$dir}/{$uuid}.pdf";
        Storage::put($path, $pdf->output());

        return $path;
    }
}
