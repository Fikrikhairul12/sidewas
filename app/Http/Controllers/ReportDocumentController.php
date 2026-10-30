<?php

namespace App\Http\Controllers;

use App\Models\DjsnKompilasi;
use App\Models\SnpKompilasi;
use App\Services\KompilasiBahanReader;
use App\Services\ReportDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportDocumentController extends Controller
{
    public function show(Request $request, int $kompilasi, string $filename, string $module): Response
    {
        abort_unless(in_array($module, ['snp', 'djsn'], true), 404);
        $permission = 'canAccess'.ucfirst($module).'Report';
        abort_unless($request->user()?->{$permission}(), 403, 'Anda tidak memiliki akses untuk membaca dokumen laporan ini.');
        abort_unless(strlen($filename) <= 180 && preg_match('/\A[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+\z/D', $filename), 404);

        $class = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;
        $compilation = $class::findOrFail($kompilasi);
        $reader = app(KompilasiBahanReader::class);
        abort_unless($reader->verified($compilation), 404, 'Bahan kompilasi belum tersedia sebagai dokumen resmi.');
        $references = collect([['document' => data_get($compilation->salinan_konteks, 'kompilasi.dokumen'), 'frozen' => true]]);
        foreach ($compilation->bahan as $material) {
            $references->push(['document' => $material->salinan['dokumen'] ?? null, 'frozen' => true]);
        }
        foreach ($compilation->butir->reviews as $review) {
            if (! $reader->matchesReview($compilation, $review)) {
                continue;
            }
            foreach ($module === 'snp' ? ['dokumen', 'dokumen_memo'] : ['dokumen'] as $field) {
                if ($review->{$field}) {
                    $references->push(['document' => ['disk' => 'public', 'path' => $review->{$field}], 'frozen' => false]);
                }
            }
        }
        $documents = app(ReportDocumentService::class);
        $matches = $references->filter(fn ($reference): bool => $documents->validReference($reference['document'])
            && basename($reference['document']['path']) === $filename)
            ->unique(fn ($reference): string => $reference['document']['disk'].':'.$reference['document']['path'])->values();
        abort_unless($matches->count() === 1, 404, 'Dokumen tidak termasuk bahan kompilasi ini.');

        return $documents->response($matches[0]['document'], frozen: $matches[0]['frozen']);
    }
}
