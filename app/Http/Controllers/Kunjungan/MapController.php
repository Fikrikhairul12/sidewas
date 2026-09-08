<?php

namespace App\Http\Controllers\Kunjungan;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Kunjungan\Unit;
use App\Services\Identity\CurrentEmployeeResolver;
use App\Services\Kunjungan\DashboardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, CurrentEmployeeResolver $resolver): View
    {
        $user = $request->user();
        $employee = $resolver->find($user);
        $applyScope = function (Builder $query) use ($user, $employee) {
            if (! $user->canViewAllVisits()) {
                $employee ? $query->participatedBy($employee->id) : $query->whereRaw('1 = 0');
            }
        };

        $units = Unit::query()->whereNotNull('latitude')->whereNotNull('longitude')
            ->withCount([
                'destinationVisits as total_visits' => $applyScope,
                'destinationVisits as year_visits' => function (Builder $query) use ($applyScope) {
                    $applyScope($query);
                    $query->whereBetween('start_at', [now()->startOfYear(), now()->endOfYear()]);
                },
                'destinationVisits as upcoming_visits' => function (Builder $query) use ($applyScope) {
                    $applyScope($query);
                    $query->where('status', VisitStatus::APPROVED->value)->where('start_at', '>', now());
                },
            ])
            ->withMax(['destinationVisits as last_visit_at' => function (Builder $query) use ($applyScope) {
                $applyScope($query);
                $query->where('start_at', '<=', now());
            }], 'start_at')
            ->withMin(['destinationVisits as next_visit_at' => function (Builder $query) use ($applyScope) {
                $applyScope($query);
                $query->where('status', VisitStatus::APPROVED->value)->where('start_at', '>', now());
            }], 'start_at')->get();

        $markers = $units->map(fn (Unit $unit) => [
            'id' => $unit->id, 'name' => $unit->nama_unit_kerja, 'city' => $unit->kab_kota,
            'province' => $unit->provinsi, 'lat' => $unit->latitude, 'lng' => $unit->longitude,
            'total' => $unit->total_visits, 'year' => $unit->year_visits, 'upcoming_count' => $unit->upcoming_visits,
            'last' => $unit->last_visit_at ? date('d-m-Y', strtotime($unit->last_visit_at)) : null,
            'next' => $unit->next_visit_at ? date('d-m-Y', strtotime($unit->next_visit_at)) : null,
            'history_url' => route('kunjungan.visits.index', ['unit_id' => $unit->id]),
        ]);

        $base = $dashboard->scopeFor($user, $employee)->with(['destinationUnit', 'destinations', 'picUnitKerja.direktorat'])->withCount('participants');
        $sections = [
            'upcoming' => (clone $base)->where('status', VisitStatus::APPROVED->value)->where('start_at', '>', now())->orderBy('start_at')->limit(4)->get(),
            'ongoing' => (clone $base)->where('status', VisitStatus::ONGOING->value)->latest('start_at')->limit(4)->get(),
            'waiting' => (clone $base)->where('status', VisitStatus::WAITING_REPORT->value)->latest('end_at')->limit(4)->get(),
            'completed' => (clone $base)->where('status', VisitStatus::COMPLETED->value)->latest('end_at')->limit(4)->get(),
        ];

        return view('kunjungan.visits.map', compact('markers', 'sections'));
    }
}
