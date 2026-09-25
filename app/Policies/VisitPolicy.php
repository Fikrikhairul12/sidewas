<?php

namespace App\Policies;

use App\Enums\VisitReportStatus;
use App\Enums\VisitStatus;
use App\Models\Kunjungan\Visit;
use App\Models\Kunjungan\VisitReport;
use App\Models\User;
use App\Services\Identity\CurrentEmployeeResolver;

class VisitPolicy
{
    public function __construct(private CurrentEmployeeResolver $employees) {}

    public function viewAny(User $user): bool
    {
        return $user->canAccessKunjungan();
    }

    public function view(User $user, Visit $visit): bool
    {
        if ($user->canViewAllVisits()) {
            return true;
        }
        $employee = $this->employees->find($user);

        return $employee && $visit->participants()->whereKey($employee->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->canCreateKunjungan() && $this->employees->find($user) !== null;
    }

    public function update(User $user, Visit $visit): bool
    {
        return ($user->isSuperAdmin() || $visit->created_by_user_id === $user->id)
            && in_array($visit->status, [VisitStatus::PENDING, VisitStatus::REJECTED], true)
            && $this->view($user, $visit);
    }

    public function approve(User $user, Visit $visit): bool
    {
        return $user->canModerateKunjungan()
            && $visit->created_by_user_id !== $user->id
            && $visit->status === VisitStatus::PENDING;
    }

    public function finish(User $user, Visit $visit): bool
    {
        return $user->canCreateKunjungan()
            && $visit->status === VisitStatus::ONGOING
            && $this->isParticipant($user, $visit);
    }

    public function uploadReport(User $user, Visit $visit): bool
    {
        if (! $user->canCreateKunjungan()
            || $visit->status !== VisitStatus::WAITING_REPORT
            || ! $this->isParticipant($user, $visit)) {
            return false;
        }

        $currentReport = $visit->currentReport()->first();

        return $currentReport === null || $currentReport->status === VisitReportStatus::Rejected;
    }

    public function reviewReport(User $user, Visit $visit, VisitReport $report): bool
    {
        return $user->canModerateKunjungan()
            && $visit->status === VisitStatus::WAITING_REPORT
            && $report->visit_id === $visit->id
            && $report->is_current
            && $report->status === VisitReportStatus::Pending;
    }

    public function cancel(User $user, Visit $visit): bool
    {
        return ($user->isSuperAdmin() || $visit->created_by_user_id === $user->id)
            && in_array($visit->status, [VisitStatus::PENDING, VisitStatus::APPROVED], true)
            && $this->view($user, $visit);
    }

    private function isParticipant(User $user, Visit $visit): bool
    {
        $employee = $this->employees->find($user);

        return $employee !== null
            && $visit->participants()->whereKey($employee->id)->exists();
    }
}
