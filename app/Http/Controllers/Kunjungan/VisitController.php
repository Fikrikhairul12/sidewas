<?php

namespace App\Http\Controllers\Kunjungan;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kunjungan\StoreVisitRequest;
use App\Http\Requests\Kunjungan\UpdateVisitRequest;
use App\Models\Kunjungan\Employee;
use App\Models\Kunjungan\Unit;
use App\Models\Kunjungan\Visit;
use App\Models\UnitKerja as SidewasUnitKerja;
use App\Models\User;
use App\Services\Identity\CurrentEmployeeResolver;
use App\Services\Kunjungan\DashboardService;
use App\Services\Kunjungan\VisitWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): View
    {
        return $this->listing($request, $dashboard, 'kunjungan.visits.index');
    }

    public function history(Request $request): RedirectResponse
    {
        return redirect()->route('kunjungan.visits.index', $request->query(), 301);
    }

    public function create(Request $request, CurrentEmployeeResolver $resolver): View
    {
        Gate::authorize('create', Visit::class);

        return view('kunjungan.visits.create', $this->formData($resolver->resolve($request->user())));
    }

    public function store(StoreVisitRequest $request, VisitWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('create', Visit::class);
        $visit = $workflow->create($request->validated(), $request->user());

        return redirect()->route('kunjungan.visits.show', $visit)->with('success', 'Kunjungan berhasil diajukan kepada moderator.');
    }

    public function show(Visit $visit): View
    {
        Gate::authorize('view', $visit);
        $visit->load(['destinationUnit', 'destinations', 'picUnitKerja.direktorat', 'participants', 'approvals', 'statusLogs', 'reports']);
        $actorIds = $visit->approvals->pluck('acted_by_user_id')
            ->merge($visit->statusLogs->pluck('actor_user_id'))->merge($visit->reports->pluck('uploaded_by_user_id'))
            ->filter()->unique();
        $actorNames = User::query()->whereIn('id', $actorIds)->pluck('name', 'id');

        return view('kunjungan.visits.show', compact('visit', 'actorNames'));
    }

    public function edit(Visit $visit, Request $request, CurrentEmployeeResolver $resolver): View
    {
        Gate::authorize('update', $visit);
        $visit->load(['participants', 'destinations', 'picUnitKerja.direktorat']);

        return view('kunjungan.visits.edit', ['visit' => $visit, ...$this->formData($resolver->resolve($request->user()))]);
    }

    public function update(UpdateVisitRequest $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        $workflow->update($visit, $request->validated(), $request->user());

        return redirect()->route('kunjungan.visits.show', $visit)->with('success', 'Perubahan berhasil disimpan.');
    }

    public function resubmit(Request $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('update', $visit);
        $workflow->resubmit($visit, $request->user());

        return back()->with('success', 'Kunjungan berhasil diajukan kembali kepada moderator.');
    }

    public function finish(Request $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('finish', $visit);
        $workflow->finish($visit, $request->user());

        return back()->with('success', 'Kunjungan ditandai selesai. Silakan unggah laporan PDF.');
    }

    public function cancel(Request $request, Visit $visit, VisitWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('cancel', $visit);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 5 karakter.',
        ]);
        $workflow->cancel($visit, $request->user(), $data['reason']);

        return back()->with('success', 'Kunjungan berhasil dibatalkan.');
    }

    private function listing(Request $request, DashboardService $dashboard, string $view): View
    {
        Gate::authorize('viewAny', Visit::class);
        $employee = app(CurrentEmployeeResolver::class)->find($request->user());
        $query = $dashboard->scopeFor($request->user(), $employee)
            ->with(['destinationUnit', 'destinations', 'picUnitKerja.direktorat', 'currentReport'])->withCount('participants');

        $query->when($request->filled('search'), function (Builder $query) use ($request) {
            $search = '%'.trim($request->string('search')).'%';
            $matchingPicUnitIds = SidewasUnitKerja::query()
                ->where(fn (Builder $unit) => $unit->where('kode_unit', 'like', $search)
                    ->orWhere('nama_unit', 'like', $search)
                    ->orWhereHas('direktorat', fn (Builder $direktorat) => $direktorat->where('nama_direktorat', 'like', $search)))
                ->pluck('id');

            $query->where(function (Builder $query) use ($search, $matchingPicUnitIds) {
                $query->where('visit_number', 'like', $search)->orWhere('title', 'like', $search)
                    ->orWhereHas('destinationUnit', fn (Builder $unit) => $unit->where('nama_unit_kerja', 'like', $search))
                    ->orWhereHas('destinations', fn (Builder $unit) => $unit->where('nama_unit_kerja', 'like', $search))
                    ->orWhereIn('pic_unit_kerja_id', $matchingPicUnitIds)
                    ->orWhereHas('participants', fn (Builder $participants) => $participants->where('name', 'like', $search));
            });
        })->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('province'), function (Builder $query) use ($request) {
                $province = $request->string('province');
                $query->where(fn (Builder $q) => $q
                    ->whereHas('destinations', fn (Builder $unit) => $unit->where('provinsi', $province))
                    ->orWhereHas('destinationUnit', fn (Builder $unit) => $unit->where('provinsi', $province)));
            })
            ->when($request->filled('unit_id'), function (Builder $query) use ($request) {
                $unitId = $request->integer('unit_id');
                $query->where(fn (Builder $q) => $q->where('destination_unit_id', $unitId)
                    ->orWhereHas('destinations', fn (Builder $unit) => $unit->whereKey($unitId)));
            })
            ->when($request->filled('pic_unit_kerja_id'), fn (Builder $query) => $query->where('pic_unit_kerja_id', $request->integer('pic_unit_kerja_id')))
            ->when($request->filled('employee_id'), function (Builder $query) use ($request) {
                $id = $request->integer('employee_id');
                $query->whereHas('participants', fn (Builder $participants) => $participants->whereKey($id));
            })
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('start_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('start_at', '<=', $request->date('date_to')));

        return view($view, [
            'visits' => $query->latest('start_at')->paginate(15)->withQueryString(),
            'statuses' => VisitStatus::cases(),
            'units' => Unit::query()->orderBy('nama_unit_kerja')->get(['id', 'nama_unit_kerja']),
            'provinces' => Unit::query()->whereNotNull('provinsi')->distinct()->orderBy('provinsi')->pluck('provinsi'),
            'employees' => Employee::query()->eligibleForVisits()->orderBy('name')->get(['id', 'name']),
            'picUnitKerjas' => $this->picUnitKerjas(),
        ]);
    }

    private function formData(Employee $current): array
    {
        return [
            'units' => Unit::query()->orderBy('nama_unit_kerja')->get(),
            'employees' => Employee::query()->eligibleForVisits()->orderBy('name')->get(),
            'picUnitKerjas' => $this->picUnitKerjas(),
            'currentEmployee' => $current,
        ];
    }

    private function picUnitKerjas()
    {
        return SidewasUnitKerja::query()
            ->with('direktorat')
            ->orderBy('nama_unit')
            ->get();
    }
}
