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

test('kunjungan integration contains the agreed roles and report invariants', function () {
    $root = dirname(__DIR__, 2);
    $userModel = file_get_contents($root.'/app/Models/User.php');
    $policy = file_get_contents($root.'/app/Policies/VisitPolicy.php');
    $workflow = file_get_contents($root.'/app/Services/Kunjungan/VisitWorkflowService.php');
    $schema = file_get_contents($root.'/database/migrations/2026_08_24_000100_create_kunjungan_tables.php');
    $accessMigration = file_get_contents($root.'/database/migrations/2026_09_08_000600_add_kunjungan_access_control.php');
    $sidebar = file_get_contents($root.'/resources/views/layouts/sidebar.blade.php');

    expect($userModel)
        ->toContain("'moderator_kunjungan'")
        ->toContain("'pic_kunjungan'")
        ->toContain("'viewer_kunjungan'")
        ->toContain('return $this->canAccessKunjungan();');

    expect($policy)
        ->toContain('$visit->created_by_user_id !== $user->id')
        ->toContain('$this->isParticipant($user, $visit)')
        ->toContain('! $visit->reports()->exists()');

    expect($workflow)
        ->toContain("storeAs('dokumen/laporan-kunjungan'")
        ->toContain('ensureActorIsParticipant')
        ->toContain('Laporan untuk kunjungan ini sudah pernah diunggah.');

    expect($schema)->toContain("\$table->unique('visit_id')");
    expect($accessMigration)
        ->toContain("'moderator' => 'Moderator Kunjungan Kerja'")
        ->toContain("'pic' => 'PIC Kunjungan Kerja'")
        ->toContain("'viewer' => 'Pegawai (Viewer) Kunjungan Kerja'");

    expect($sidebar)
        ->toContain('Dashboard Kunjungan')
        ->toContain('Daftar Kunjungan')
        ->toContain('Ajukan Kunjungan')
        ->toContain('Kalender Kunjungan')
        ->toContain('Peta Kunjungan')
        ->toContain('Persetujuan');
});
