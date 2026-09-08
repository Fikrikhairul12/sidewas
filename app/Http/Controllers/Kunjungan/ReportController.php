<?php

namespace App\Http\Controllers\Kunjungan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kunjungan\UploadVisitReportRequest;
use App\Models\Kunjungan\Visit;
use App\Models\Kunjungan\VisitReport;
use App\Services\Kunjungan\VisitWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function store(UploadVisitReportRequest $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        $workflow->uploadReport($visit, $request->file('report'), $request->user());

        return back()->with('success', 'Laporan berhasil diunggah.');
    }

    public function show(Visit $visit, VisitReport $report): BinaryFileResponse
    {
        Gate::authorize('view', $visit);
        $this->ensureReportBelongsToVisit($visit, $report);
        abort_unless(Storage::disk('public')->exists($report->file_path), 404, 'File laporan tidak ditemukan.');

        return response()->file(Storage::disk('public')->path($report->file_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $report->original_filename).'"',
        ]);
    }

    public function download(Visit $visit, VisitReport $report): StreamedResponse
    {
        Gate::authorize('view', $visit);
        $this->ensureReportBelongsToVisit($visit, $report);
        abort_unless(Storage::disk('public')->exists($report->file_path), 404, 'File laporan tidak ditemukan.');

        return Storage::disk('public')->download($report->file_path, $report->original_filename, ['Content-Type' => 'application/pdf']);
    }

    private function ensureReportBelongsToVisit(Visit $visit, VisitReport $report): void
    {
        abort_unless($report->visit_id === $visit->id, 404);
    }
}
