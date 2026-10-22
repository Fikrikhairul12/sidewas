<?php

namespace App\Http\Controllers;

use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Services\ReviuPutaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowImageController extends Controller
{
    public function snpKompilasi(Request $request, SnpButir $butir): JsonResponse
    {
        abort_unless($request->user()?->canCreateSnpKompilasi(), 403);
        if (ReviuPutaranService::enabled()) {
            $this->assertCompilationRound($request, $butir);

            return $this->store($request, $butir->record?->id, 'snp');
        }
        $context = $request->validate([
            'tahap_kompilasi' => ['required', 'in:tanggapan,tindak_lanjut'],
            'putaran_tl' => ['required', 'integer', 'min:1'],
        ]);
        $locked = $butir->kompilasis()
            ->where('tahap_kompilasi', $context['tahap_kompilasi'])
            ->where('putaran_tl', $context['putaran_tl'])
            ->where('status', 'dalam_proses_reviu_dewas')
            ->exists();
        abort_if($locked, 422, 'Kompilasi ini sudah masuk proses reviu.');

        return $this->store($request, $butir->record?->id, 'snp');
    }

    public function snpReviu(Request $request, SnpReview $review): JsonResponse
    {
        abort_unless($request->user()?->canReviewSnpByKomite($review->komite_id), 403);
        if (ReviuPutaranService::enabled()) {
            abort_unless(app(ReviuPutaranService::class)->canReview($request->user(), $review), 422, 'Reviu ini hanya dapat dibaca.');

            return $this->store($request, $review->butir?->record?->id, 'snp');
        }
        abort_if(
            $review->status === 'dalam_proses_tindak_lanjut_direksi'
            || ($review->tahap_review === 'tindak_lanjut' && $review->status === 'selesai_tuntas'),
            422, 'Reviu ini sudah selesai diproses dan tidak dapat diubah.'
        );

        return $this->store($request, $review->butir?->record?->id, 'snp');
    }

    public function djsnReviu(Request $request, DjsnReview $review): JsonResponse
    {
        abort_unless($request->user()?->canReviewDjsnByKomite($review->komite_id), 403);
        if (ReviuPutaranService::enabled()) {
            abort_unless(app(ReviuPutaranService::class)->canReview($request->user(), $review), 422, 'Reviu ini hanya dapat dibaca.');

            return $this->store($request, $review->butir?->record?->id, 'djsn');
        }
        abort_if(
            ($review->tahap_review === 'tanggapan' && $review->status === 'dalam_proses_tindak_lanjut_direksi')
            || ($review->tahap_review === 'tindak_lanjut' && $review->status === 'selesai_tuntas'),
            422, 'Reviu ini sudah selesai diproses dan tidak dapat diubah.'
        );
        abort_if(
            ($review->tahap_review === 'tanggapan' && empty($review->id_tanggapan))
            || ($review->tahap_review === 'tindak_lanjut' && empty($review->id_tindak_lanjut)),
            422, 'Data reviu tidak valid.'
        );

        return $this->store($request, $review->butir?->record?->id, 'djsn');
    }

    public function djsnKompilasi(Request $request, DjsnButir $butir): JsonResponse
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        abort_unless($request->user()?->canCreateDjsnKompilasi(), 403);
        $this->assertCompilationRound($request, $butir);

        return $this->store($request, $butir->record?->id, 'djsn');
    }

    private function assertCompilationRound(Request $request, SnpButir|DjsnButir $butir): void
    {
        $data = $request->validate(['putaran_id' => ['nullable', 'integer', 'min:1']]);
        app(ReviuPutaranService::class)->assertCanUploadCompilation($request->user(), $butir, isset($data['putaran_id']) ? (int) $data['putaran_id'] : null);
    }

    private function store(Request $request, ?int $recordId, string $module): JsonResponse
    {
        abort_unless($recordId, 422, 'Surat tidak tersedia.');
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $file = $validated['image'];
        $filename = Str::uuid().($file->getMimeType() === 'image/png' ? '.png' : '.jpg');
        $path = $file->storeAs($module.'-images/'.$recordId, $filename, 'local');
        abort_unless($path, 500, 'Gambar gagal disimpan. Silakan coba lagi.');

        return response()->json(['url' => route($module.'.butir-images.show', ['record' => $recordId, 'filename' => $filename], false)], 201);
    }
}
