<?php

use App\Enums\VisitReportStatus;
use App\Enums\VisitStatus;
use App\Models\Kunjungan\Visit;
use App\Models\Kunjungan\VisitReport;
use App\Models\User;
use App\Policies\VisitPolicy;
use App\Services\Identity\CurrentEmployeeResolver;

afterEach(fn () => Mockery::close());

test('visit report statuses expose the labels used by the approval interface', function () {
    expect(VisitReportStatus::Pending->label())->toBe('Menunggu Persetujuan')
        ->and(VisitReportStatus::Approved->label())->toBe('Disetujui')
        ->and(VisitReportStatus::Rejected->label())->toBe('Ditolak');
});

test('moderator can only review the current pending report of a waiting visit', function () {
    $resolver = Mockery::mock(CurrentEmployeeResolver::class);
    $policy = new VisitPolicy($resolver);
    $moderator = Mockery::mock(User::class)->makePartial();
    $moderator->shouldReceive('canModerateKunjungan')->times(3)->andReturnTrue();

    $visit = new Visit;
    $visit->forceFill(['id' => 10, 'status' => VisitStatus::WAITING_REPORT]);

    $pendingReport = new VisitReport;
    $pendingReport->forceFill([
        'visit_id' => 10,
        'status' => VisitReportStatus::Pending,
        'is_current' => true,
    ]);

    $rejectedReport = new VisitReport;
    $rejectedReport->forceFill([
        'visit_id' => 10,
        'status' => VisitReportStatus::Rejected,
        'is_current' => true,
    ]);

    $oldPendingReport = new VisitReport;
    $oldPendingReport->forceFill([
        'visit_id' => 10,
        'status' => VisitReportStatus::Pending,
        'is_current' => false,
    ]);

    expect($policy->reviewReport($moderator, $visit, $pendingReport))->toBeTrue()
        ->and($policy->reviewReport($moderator, $visit, $rejectedReport))->toBeFalse()
        ->and($policy->reviewReport($moderator, $visit, $oldPendingReport))->toBeFalse();
});

test('kunjungan roles follow the brd access boundaries', function () {
    $viewer = Mockery::mock(User::class)->makePartial();
    $viewer->shouldReceive('canAccessKunjungan')->twice()->andReturnTrue();
    $viewer->shouldReceive('isSuperAdmin')->once()->andReturnFalse();
    $viewer->shouldReceive('isKunjunganAdmin')->once()->andReturnFalse();
    $viewer->shouldReceive('isKunjunganModerator')->once()->andReturnFalse();

    $moderator = Mockery::mock(User::class)->makePartial();
    $moderator->shouldReceive('canAccessKunjungan')->twice()->andReturnTrue();
    $moderator->shouldReceive('isSuperAdmin')->once()->andReturnFalse();
    $moderator->shouldReceive('isKunjunganAdmin')->once()->andReturnFalse();
    $moderator->shouldReceive('isKunjunganModerator')->once()->andReturnTrue();

    $admin = Mockery::mock(User::class)->makePartial();
    $admin->shouldReceive('canAccessKunjungan')->twice()->andReturnTrue();
    $admin->shouldReceive('isSuperAdmin')->once()->andReturnFalse();
    $admin->shouldReceive('isKunjunganAdmin')->once()->andReturnTrue();

    expect($viewer->canCreateKunjungan())->toBeTrue()
        ->and($viewer->canViewAllVisits())->toBeFalse()
        ->and($moderator->canCreateKunjungan())->toBeTrue()
        ->and($moderator->canViewAllVisits())->toBeTrue()
        ->and($admin->canCreateKunjungan())->toBeTrue()
        ->and($admin->canViewAllVisits())->toBeTrue();
});

test('dashboard search and change history include the brd alignment fields', function () {
    $root = dirname(__DIR__, 2);
    $dashboard = file_get_contents($root.'/app/Services/Kunjungan/DashboardService.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Kunjungan/VisitController.php');
    $workflow = file_get_contents($root.'/app/Services/Kunjungan/VisitWorkflowService.php');
    $statusLog = file_get_contents($root.'/app/Models/Kunjungan/VisitStatusLog.php');

    expect($dashboard)
        ->toContain("'completed' =>")
        ->toContain('VisitReportStatus::Pending')
        ->toContain('VisitReportStatus::Rejected')
        ->and($controller)->toContain("->orWhere('purpose', 'like', \$search)")
        ->and($workflow)
        ->toContain('Data kunjungan diperbarui.')
        ->toContain('changedVisitData')
        ->and($statusLog)->toContain("'changes' => 'array'");
});
