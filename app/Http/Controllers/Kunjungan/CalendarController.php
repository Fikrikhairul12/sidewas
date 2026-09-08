<?php

namespace App\Http\Controllers\Kunjungan;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Kunjungan\Employee;
use App\Services\Identity\CurrentEmployeeResolver;
use App\Services\Kunjungan\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        CurrentEmployeeResolver $resolver,
    ): View {
        $monthInput = (string) $request->string('month');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthInput)
            ? CarbonImmutable::createFromFormat('Y-m-d', $monthInput.'-01')->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
        $gridStart = $month->startOfWeek();
        $gridEnd = $month->endOfMonth()->endOfWeek();
        $user = $request->user();
        $employee = $resolver->find($user);

        $query = $dashboard->scopeFor($user, $employee)
            ->with(['destinationUnit', 'destinations', 'picUnitKerja.direktorat'])
            ->withCount('participants')
            ->whereIn('status', [
                VisitStatus::PENDING->value,
                VisitStatus::APPROVED->value,
                VisitStatus::ONGOING->value,
                VisitStatus::WAITING_REPORT->value,
                VisitStatus::COMPLETED->value,
            ])
            ->where('start_at', '<=', $gridEnd->endOfDay())
            ->where('end_at', '>=', $gridStart->startOfDay());

        $selectedEmployee = null;
        if ($user->canViewAllVisits() && $request->filled('employee_id')) {
            $selectedEmployee = Employee::query()->eligibleForVisits()->find($request->integer('employee_id'));

            if ($selectedEmployee) {
                $query->whereHas('participants', fn (Builder $participants) => $participants->whereKey($selectedEmployee->id));
            }
        }

        $visits = $query->orderBy('start_at')->get();
        $days = $this->calendarDays($gridStart, $gridEnd);
        $eventsByDate = $days->mapWithKeys(function (CarbonImmutable $day) use ($visits) {
            $events = $visits->filter(fn ($visit) => $visit->start_at->lte($day->endOfDay())
                && $visit->end_at->gte($day->startOfDay()));

            return [$day->toDateString() => $events];
        });

        return view('kunjungan.visits.calendar', [
            'month' => $month,
            'days' => $days,
            'eventsByDate' => $eventsByDate,
            'visits' => $visits,
            'selectedEmployee' => $selectedEmployee,
            'employees' => $user->canViewAllVisits()
                ? Employee::query()->eligibleForVisits()->orderBy('name')->get(['id', 'name', 'organizational_unit'])
                : collect(),
        ]);
    }

    private function calendarDays(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $days = collect();

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days->push($day);
        }

        return $days;
    }
}
