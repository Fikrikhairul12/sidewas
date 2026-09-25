<?php

namespace App\Http\Controllers\Kunjungan;

use App\Enums\VisitReportStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kunjungan\RejectVisitReportRequest;
use App\Http\Requests\Kunjungan\RejectVisitRequest;
use App\Models\Kunjungan\Visit;
use App\Models\Kunjungan\VisitApproval;
use App\Models\Kunjungan\VisitReport;
use App\Services\Kunjungan\VisitWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canModerateKunjungan(), 403);

        $visits = Visit::query()->with(['destinationUnit', 'destinations', 'picUnitKerja.direktorat'])->withCount('participants')
            ->where('status', VisitStatus::PENDING->value)
            ->where('created_by_user_id', '!=', $request->user()->id)
            ->oldest('submitted_at')->paginate(15);
        $reports = VisitReport::query()
            ->with(['visit.destinationUnit', 'visit.destinations', 'visit.picUnitKerja.direktorat'])
            ->where('status', VisitReportStatus::Pending->value)
            ->where('is_current', true)
            ->whereHas('visit', fn ($query) => $query->where('status', VisitStatus::WAITING_REPORT->value))
            ->oldest('uploaded_at')
            ->paginate(15, ['*'], 'report_page');
        $stats = [
            'pending' => Visit::query()->where('status', VisitStatus::PENDING->value)
                ->where('created_by_user_id', '!=', $request->user()->id)->count(),
            'approved' => VisitApproval::query()->where('decision', VisitStatus::APPROVED->value)
                ->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'rejected' => VisitApproval::query()->where('decision', VisitStatus::REJECTED->value)
                ->whereBetween('decided_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'reports_pending' => VisitReport::query()
                ->where('status', VisitReportStatus::Pending->value)
                ->where('is_current', true)
                ->whereHas('visit', fn ($query) => $query->where('status', VisitStatus::WAITING_REPORT->value))
                ->count(),
        ];

        return view('kunjungan.approvals.index', compact('visits', 'reports', 'stats'));
    }

    public function approve(Request $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('approve', $visit);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->approve($visit, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Pengajuan kunjungan berhasil disetujui.');
    }

    public function reject(RejectVisitRequest $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        $workflow->reject($visit, $request->user(), $request->validated('notes'));

        return back()->with('success', 'Pengajuan kunjungan ditolak dan dikembalikan kepada pegawai.');
    }

    public function approveReport(
        Request $request,
        Visit $visit,
        VisitReport $report,
        VisitWorkflowService $workflow,
    ): RedirectResponse {
        Gate::authorize('reviewReport', [$visit, $report]);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $workflow->approveReport($visit, $report, $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Laporan kunjungan disetujui dan kunjungan dinyatakan selesai.');
    }

    public function rejectReport(
        RejectVisitReportRequest $request,
        Visit $visit,
        VisitReport $report,
        VisitWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->rejectReport($visit, $report, $request->user(), $request->validated('notes'));

        return back()->with('success', 'Laporan ditolak dan dikembalikan kepada pegawai untuk diperbaiki.');
    }
}
