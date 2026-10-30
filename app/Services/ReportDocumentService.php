<?php

namespace App\Services;

use App\Models\DjsnKompilasi;
use App\Models\SnpKompilasi;
use App\Models\SuratTahap;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class ReportDocumentService
{
    /** @param array<string, array<string, mixed>|null> $copies */
    public function documents(SnpKompilasi|DjsnKompilasi $compilation, array $copies): Collection
    {
        $module = SuratTahap::moduleFor($compilation->getConnectionName());

        return collect($copies)->filter(fn ($copy): bool => $this->validReference($copy))
            ->map(fn ($copy, $label): array => [
                'label' => $label,
                'url' => route($module.'.report.dokumen', ['kompilasi' => $compilation->id, 'filename' => basename($copy['path'])]),
            ])->values();
    }

    public function validReference(mixed $document): bool
    {
        return is_array($document) && in_array($document['disk'] ?? null, ['local', 'public'], true)
            && is_string($document['path'] ?? null)
            && preg_match('~\Adokumen/[a-zA-Z0-9_./-]+\z~D', $document['path']) === 1
            && ! str_contains($document['path'], '..') && ! str_contains($document['path'], '//');
    }

    /** @param array<string, mixed>|null $document */
    public function response(?array $document, bool $download = false, bool $frozen = true): Response
    {
        if (! $this->validReference($document)) {
            return $this->unavailable();
        }
        try {
            $disk = Storage::disk($document['disk']);
            $bytes = $disk->exists($document['path']) ? $disk->get($document['path']) : null;
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
        if (! is_string($bytes)) {
            return $this->unavailable();
        }
        if ($frozen && (! is_string($document['sha256'] ?? null)
            || ! hash_equals($document['sha256'], hash('sha256', $bytes)))) {
            return $this->unavailable(409);
        }
        $filename = basename($document['path']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $type = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
        $disposition = ! $download && in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true) ? 'inline' : 'attachment';

        return response($bytes, 200, [
            'Content-Type' => $type,
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function unavailable(int $status = 404): Response
    {
        return response()->view('report-document-unavailable', ['verificationFailed' => $status === 409], $status,
            ['Cache-Control' => 'private, no-store']);
    }
}
