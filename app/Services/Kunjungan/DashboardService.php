<?php

namespace App\Services\Kunjungan;

use App\Enums\VisitStatus;
use App\Models\Kunjungan\Employee;
use App\Models\Kunjungan\Visit;
use App\Models\User;
use App\Services\Identity\CurrentEmployeeResolver;
use App\Services\Identity\SidewasEmployeeSynchronizer;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        private CurrentEmployeeResolver $employees,
        private SidewasEmployeeSynchronizer $directory,
    ) {}

    public function data(User $user): array
    {
        $employee = $this->employees->find($user);
        $base = $this->scopeFor($user, $employee);
        $trackedStatuses = [
            VisitStatus::PENDING->value, VisitStatus::REJECTED->value,
            VisitStatus::APPROVED->value, VisitStatus::ONGOING->value,
            VisitStatus::WAITING_REPORT->value, VisitStatus::COMPLETED->value,
        ];
        $stats = [
            'total' => (clone $base)->whereIn('status', $trackedStatuses)->count(),
            'month' => (clone $base)->whereIn('status', $trackedStatuses)->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'pending' => (clone $base)->where('status', VisitStatus::PENDING->value)->count(),
            'ongoing' => (clone $base)->where('status', VisitStatus::ONGOING->value)->count(),
            'waiting_report' => (clone $base)->where('status', VisitStatus::WAITING_REPORT->value)->count(),
            'upcoming' => (clone $base)->where('status', VisitStatus::APPROVED->value)->where('start_at', '>', now())->count(),
        ];
        $upcoming = (clone $base)->with(['destinationUnit', 'destinations', 'picUnitKerja.direktorat'])->withCount('participants')
            ->where('status', VisitStatus::APPROVED->value)->where('start_at', '>', now())->orderBy('start_at')->first();
        $recent = (clone $base)->with(['destinationUnit', 'destinations', 'picUnitKerja.direktorat'])->withCount('participants')->latest('start_at')->limit(5)->get();
        $reportRequired = (clone $base)->whereIn('status', [
            VisitStatus::WAITING_REPORT->value,
            VisitStatus::COMPLETED->value,
        ])->count();
        $reportsUploaded = (clone $base)->whereIn('status', [
            VisitStatus::WAITING_REPORT->value,
            VisitStatus::COMPLETED->value,
        ])->whereHas('currentReport')->count();
        $outstandingReports = (clone $base)->with(['destinationUnit', 'destinations'])
            ->where('status', VisitStatus::WAITING_REPORT->value)
            ->oldest('end_at')->limit(3)->get();

        return [
            'stats' => $stats,
            'upcomingVisit' => $upcoming,
            'recentVisits' => $recent,
            'chartData' => $this->monthlyCounts((clone $base)->whereIn('status', $trackedStatuses)),
            'employee' => $employee,
            'reportHealth' => [
                'required' => $reportRequired,
                'uploaded' => $reportsUploaded,
                'missing' => $stats['waiting_report'],
                'percentage' => $reportRequired > 0 ? (int) round(($reportsUploaded / $reportRequired) * 100) : 100,
            ],
            'outstandingReports' => $outstandingReports,
        ];
    }

    public function scopeFor(User $user, ?Employee $employee = null): Builder
    {
        $this->directory->syncAll();
        $query = Visit::query();

        if ($user->canViewAllVisits()) {
            return $query;
        }

        return $employee ? $query->participatedBy($employee->id) : $query->whereRaw('1 = 0');
    }

    private function monthlyCounts(Builder $base): array
    {
        $driver = $base->getModel()->getConnection()->getDriverName();
        $monthSql = $driver === 'sqlite' ? "CAST(strftime('%m', start_at) AS INTEGER)" : 'MONTH(start_at)';
        $rows = (clone $base)->whereBetween('start_at', [now()->startOfYear(), now()->endOfYear()])
            ->selectRaw("{$monthSql} as month_number, count(*) as aggregate")
            ->groupByRaw($monthSql)->pluck('aggregate', 'month_number');

        return collect(range(1, 12))->map(fn ($month) => (int) ($rows[$month] ?? 0))->all();
    }
}
