<?php

use App\Enums\VisitStatus;
use App\Models\Kunjungan\Visit;
use App\Models\User;
use App\Policies\VisitPolicy;
use App\Services\Identity\CurrentEmployeeResolver;

afterEach(fn () => Mockery::close());

test('moderator cannot approve a visit they submitted themselves', function () {
    $resolver = Mockery::mock(CurrentEmployeeResolver::class);
    $policy = new VisitPolicy($resolver);
    $moderator = Mockery::mock(User::class)->makePartial();
    $moderator->id = 17;
    $moderator->shouldReceive('canModerateKunjungan')->twice()->andReturnTrue();

    $ownVisit = new Visit;
    $ownVisit->forceFill([
        'created_by_user_id' => 17,
        'status' => VisitStatus::PENDING,
    ]);

    $otherVisit = new Visit;
    $otherVisit->forceFill([
        'created_by_user_id' => 18,
        'status' => VisitStatus::PENDING,
    ]);

    expect($policy->approve($moderator, $ownVisit))->toBeFalse()
        ->and($policy->approve($moderator, $otherVisit))->toBeTrue();
});

test('kunjungan integration contains the brd roles and report approval workflow', function () {
    $root = dirname(__DIR__, 2);
    $userModel = file_get_contents($root.'/app/Models/User.php');
    $policy = file_get_contents($root.'/app/Policies/VisitPolicy.php');
    $workflow = file_get_contents($root.'/app/Services/Kunjungan/VisitWorkflowService.php');
    $accessMigration = file_get_contents($root.'/database/migrations/2026_09_08_000600_add_kunjungan_access_control.php');
    $adminMigration = file_get_contents($root.'/database/migrations/2026_09_25_151356_add_admin_kunjungan_role_type.php');
    $reportMigration = file_get_contents($root.'/database/migrations/2026_09_25_151347_add_report_review_workflow_to_visit_reports_table.php');
    $sidebar = file_get_contents($root.'/resources/views/layouts/sidebar.blade.php');

    expect($userModel)
        ->toContain("'admin_kunjungan'")
        ->toContain("'moderator_kunjungan'")
        ->toContain("'pic_kunjungan'")
        ->toContain("'viewer_kunjungan'")
        ->toContain('return $this->canAccessKunjungan();')
        ->toContain('$this->isSuperAdmin() || $this->isKunjunganAdmin() || $this->isKunjunganModerator()');

    expect($policy)
        ->toContain('$visit->created_by_user_id !== $user->id')
        ->toContain('$this->isParticipant($user, $visit)')
        ->toContain('VisitReportStatus::Rejected')
        ->toContain('public function reviewReport');

    expect($workflow)
        ->toContain("storeAs('dokumen/laporan-kunjungan'")
        ->toContain('ensureActorIsParticipant')
        ->toContain('public function approveReport')
        ->toContain('public function rejectReport')
        ->toContain("'status' => VisitReportStatus::Pending")
        ->toContain("'status' => VisitReportStatus::Approved")
        ->toContain("'status' => VisitReportStatus::Rejected");

    expect($accessMigration)
        ->toContain("'moderator' => 'Moderator Kunjungan Kerja'")
        ->toContain("'pic' => 'PIC Kunjungan Kerja'")
        ->toContain("'viewer' => 'Pegawai (Viewer) Kunjungan Kerja'");
    expect($adminMigration)->toContain("'name' => 'admin_kunjungan'");
    expect($reportMigration)
        ->toContain("\$table->dropUnique(\$index['name'])")
        ->toContain("\$table->unique(['visit_id', 'version']");

    expect($sidebar)
        ->toContain('Dashboard Kunjungan')
        ->toContain('Daftar Kunjungan')
        ->toContain('Ajukan Kunjungan')
        ->toContain('Kalender Kunjungan')
        ->toContain('Peta Kunjungan')
        ->toContain('Persetujuan')
        ->not->toContain('Direktori Pegawai')
        ->not->toContain("route('kunjungan.employees.index')");
});
