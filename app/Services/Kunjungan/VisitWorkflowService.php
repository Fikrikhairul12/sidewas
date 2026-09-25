<?php

namespace App\Services\Kunjungan;

use App\Enums\VisitReportStatus;
use App\Enums\VisitStatus;
use App\Models\Kunjungan\Employee;
use App\Models\Kunjungan\Visit;
use App\Models\Kunjungan\VisitApproval;
use App\Models\Kunjungan\VisitReport;
use App\Models\Kunjungan\VisitStatusLog;
use App\Models\User;
use App\Services\Identity\CurrentEmployeeResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VisitWorkflowService
{
    public function __construct(private CurrentEmployeeResolver $employees) {}

    public function create(array $data, User $user): Visit
    {
        return DB::connection('mysql_kunjungan')->transaction(function () use ($data, $user) {
            $destinationIds = collect($data['destination_unit_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $participantIds = collect($data['participant_ids'] ?? [])->map(fn ($id) => (int) $id);
            $participantIds->push($this->employees->resolve($user)->id);
            $this->ensureParticipantsAreEligible($participantIds);

            $visit = Visit::query()->create([
                ...collect($data)->except(['participant_ids', 'destination_unit_ids'])->all(),
                'destination_unit_id' => $destinationIds->first(),
                'visit_number' => $this->nextVisitNumber(),
                'created_by_user_id' => $user->id,
                'status' => VisitStatus::PENDING,
                'approval_round' => 1,
                'submitted_at' => now(),
            ]);
            $visit->destinations()->sync($destinationIds);
            $visit->participants()->sync($participantIds->unique()->values());
            $this->log($visit, null, VisitStatus::PENDING, $user->id, 'Kunjungan diajukan kepada moderator.');

            return $visit;
        });
    }

    public function update(Visit $visit, array $data, User $user): Visit
    {
        return DB::connection('mysql_kunjungan')->transaction(function () use ($visit, $data, $user) {
            $before = $this->visitSnapshot($visit);
            $destinationIds = collect($data['destination_unit_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $participantIds = collect($data['participant_ids'] ?? [])->map(fn ($id) => (int) $id);
            $participantIds->push($this->employees->resolve($user)->id);
            $this->ensureParticipantsAreEligible($participantIds);
            $visit->update([
                ...collect($data)->except(['participant_ids', 'destination_unit_ids'])->all(),
                'destination_unit_id' => $destinationIds->first(),
            ]);
            $visit->destinations()->sync($destinationIds);
            $visit->participants()->sync($participantIds->unique()->values());
            $visit->refresh();
            $changes = $this->changedVisitData($before, $this->visitSnapshot($visit));

            if ($changes !== []) {
                $this->log(
                    $visit,
                    $visit->status,
                    $visit->status,
                    $user->id,
                    'Data kunjungan diperbarui.',
                    $changes,
                );
            }

            return $visit;
        });
    }

    public function approve(Visit $visit, User $actor, ?string $notes = null): Visit
    {
        return $this->decide($visit, $actor, VisitStatus::APPROVED, $notes);
    }

    public function reject(Visit $visit, User $actor, string $notes): Visit
    {
        return $this->decide($visit, $actor, VisitStatus::REJECTED, $notes);
    }

    public function resubmit(Visit $visit, User $actor): Visit
    {
        if ($visit->status !== VisitStatus::REJECTED) {
            throw ValidationException::withMessages([
                'status' => 'Hanya kunjungan yang ditolak dapat diajukan kembali.',
            ]);
        }

        return DB::connection('mysql_kunjungan')->transaction(function () use ($visit, $actor) {
            $from = $visit->status;
            $visit->update([
                'status' => VisitStatus::PENDING,
                'approval_round' => $visit->approval_round + 1,
                'submitted_at' => now(),
            ]);
            $this->log($visit, $from, VisitStatus::PENDING, $actor->id, 'Pengajuan diperbaiki dan dikirim kembali kepada moderator.');

            return $visit->refresh();
        });
    }

    public function finish(Visit $visit, User $actor): Visit
    {
        $this->ensureActorIsParticipant($visit, $actor);

        return $this->transition($visit, VisitStatus::ONGOING, VisitStatus::WAITING_REPORT, $actor->id, 'Pegawai menandai kunjungan selesai dilaksanakan.');
    }

    public function cancel(Visit $visit, User $actor, string $reason): Visit
    {
        if (! in_array($visit->status, [VisitStatus::PENDING, VisitStatus::APPROVED], true)) {
            throw ValidationException::withMessages(['status' => 'Hanya pengajuan yang menunggu persetujuan atau sudah disetujui yang dapat dibatalkan.']);
        }
        $from = $visit->status;
        $visit->update(['status' => VisitStatus::CANCELLED, 'cancelled_reason' => $reason]);
        $this->log($visit, $from, VisitStatus::CANCELLED, $actor->id, 'Kunjungan dibatalkan. Alasan: '.$reason);

        return $visit->refresh();
    }

    public function startDueVisits(): int
    {
        $count = 0;
        Visit::query()->where('status', VisitStatus::APPROVED->value)->where('start_at', '<=', now())
            ->chunkById(100, function ($visits) use (&$count) {
                foreach ($visits as $visit) {
                    $this->transition($visit, VisitStatus::APPROVED, VisitStatus::ONGOING, null, 'Kunjungan dimulai otomatis sesuai jadwal.');
                    $count++;
                }
            });

        return $count;
    }

    public function uploadReport(Visit $visit, UploadedFile $file, User $actor): VisitReport
    {
        $this->ensureActorIsParticipant($visit, $actor);

        if ($visit->status !== VisitStatus::WAITING_REPORT) {
            throw ValidationException::withMessages(['report' => 'Laporan hanya dapat diunggah setelah kunjungan ditandai selesai.']);
        }

        return DB::connection('mysql_kunjungan')->transaction(function () use ($visit, $file, $actor) {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $currentReport = $lockedVisit->reports()->where('is_current', true)->lockForUpdate()->first();

            if ($currentReport && $currentReport->status !== VisitReportStatus::Rejected) {
                throw ValidationException::withMessages([
                    'report' => $currentReport->status === VisitReportStatus::Pending
                        ? 'Laporan sedang menunggu persetujuan moderator.'
                        : 'Laporan kunjungan ini sudah disetujui.',
                ]);
            }

            $currentReport?->update(['is_current' => false]);
            $version = ((int) $lockedVisit->reports()->max('version')) + 1;
            $storedName = $lockedVisit->visit_number.'-'.Str::uuid().'.pdf';
            $path = $file->storeAs('dokumen/laporan-kunjungan', $storedName, 'public');
            $report = $lockedVisit->reports()->create([
                'version' => $version,
                'status' => VisitReportStatus::Pending,
                'original_filename' => Str::limit(basename($file->getClientOriginalName()), 240, ''),
                'stored_filename' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'file_size' => $file->getSize(),
                'uploaded_by_user_id' => $actor->id,
                'is_current' => true,
                'uploaded_at' => now(),
            ]);

            $this->log(
                $lockedVisit,
                VisitStatus::WAITING_REPORT,
                VisitStatus::WAITING_REPORT,
                $actor->id,
                "Laporan PDF versi {$version} diunggah dan menunggu persetujuan moderator.",
            );

            return $report;
        });
    }

    public function approveReport(Visit $visit, VisitReport $report, User $actor, ?string $notes = null): Visit
    {
        return DB::connection('mysql_kunjungan')->transaction(function () use ($visit, $report, $actor, $notes) {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $lockedReport = VisitReport::query()->lockForUpdate()->findOrFail($report->id);
            $this->ensureReportCanBeReviewed($lockedVisit, $lockedReport);

            $lockedReport->update([
                'status' => VisitReportStatus::Approved,
                'reviewed_by_user_id' => $actor->id,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $lockedVisit->update(['status' => VisitStatus::COMPLETED]);
            $this->log(
                $lockedVisit,
                VisitStatus::WAITING_REPORT,
                VisitStatus::COMPLETED,
                $actor->id,
                "Moderator menyetujui laporan PDF versi {$lockedReport->version}.",
            );

            return $lockedVisit->refresh();
        });
    }

    public function rejectReport(Visit $visit, VisitReport $report, User $actor, string $notes): VisitReport
    {
        return DB::connection('mysql_kunjungan')->transaction(function () use ($visit, $report, $actor, $notes) {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $lockedReport = VisitReport::query()->lockForUpdate()->findOrFail($report->id);
            $this->ensureReportCanBeReviewed($lockedVisit, $lockedReport);

            $lockedReport->update([
                'status' => VisitReportStatus::Rejected,
                'reviewed_by_user_id' => $actor->id,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);
            $this->log(
                $lockedVisit,
                VisitStatus::WAITING_REPORT,
                VisitStatus::WAITING_REPORT,
                $actor->id,
                "Moderator menolak laporan PDF versi {$lockedReport->version}. Alasan: {$notes}",
            );

            return $lockedReport->refresh();
        });
    }

    private function transition(Visit $visit, VisitStatus $expected, VisitStatus $next, ?int $actorId, string $notes): Visit
    {
        if ($visit->status !== $expected) {
            throw ValidationException::withMessages(['status' => "Perubahan status {$expected->label()} ke {$next->label()} tidak valid."]);
        }
        $visit->update(['status' => $next]);
        $this->log($visit, $expected, $next, $actorId, $notes);

        return $visit->refresh();
    }

    private function decide(Visit $visit, User $actor, VisitStatus $decision, ?string $notes): Visit
    {
        if ($visit->created_by_user_id === $actor->id) {
            throw ValidationException::withMessages([
                'approval' => 'Pengaju tidak dapat menyetujui atau menolak kunjungan yang dibuatnya sendiri.',
            ]);
        }

        if ($visit->status !== VisitStatus::PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Hanya kunjungan yang menunggu persetujuan dapat diproses.',
            ]);
        }

        return DB::connection('mysql_kunjungan')->transaction(function () use ($visit, $actor, $decision, $notes) {
            $from = $visit->status;
            VisitApproval::query()->create([
                'visit_id' => $visit->id,
                'round' => $visit->approval_round,
                'decision' => $decision->value,
                'acted_by_user_id' => $actor->id,
                'notes' => $notes,
                'decided_at' => now(),
            ]);
            $visit->update(['status' => $decision]);
            $message = $decision === VisitStatus::APPROVED
                ? 'Moderator menyetujui pengajuan kunjungan.'
                : 'Moderator menolak pengajuan kunjungan.';
            $this->log($visit, $from, $decision, $actor->id, $message.($notes ? ' Catatan: '.$notes : ''));

            return $visit->refresh();
        });
    }

    private function nextVisitNumber(): string
    {
        $year = now()->format('Y');
        $last = Visit::query()->where('visit_number', 'like', "KUNJ-{$year}-%")
            ->lockForUpdate()->orderByDesc('visit_number')->value('visit_number');
        $sequence = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('KUNJ-%s-%05d', $year, $sequence);
    }

    private function ensureParticipantsAreEligible(Collection $participantIds): void
    {
        $participantIds = $participantIds->unique()->values();
        $eligibleIds = Employee::query()->eligibleForVisits()->whereKey($participantIds)->pluck('id');

        if ($eligibleIds->count() !== $participantIds->count()) {
            throw ValidationException::withMessages([
                'participant_ids' => 'Semua peserta harus merupakan pengguna aktif yang memiliki role Kunjungan Kerja.',
            ]);
        }
    }

    private function ensureActorIsParticipant(Visit $visit, User $actor): void
    {
        $employee = $this->employees->find($actor);

        if ($employee === null || ! $visit->participants()->whereKey($employee->id)->exists()) {
            throw ValidationException::withMessages([
                'participant' => 'Tindakan ini hanya dapat dilakukan oleh anggota kunjungan.',
            ]);
        }
    }

    private function ensureReportCanBeReviewed(Visit $visit, VisitReport $report): void
    {
        if ($report->visit_id !== $visit->id
            || ! $report->is_current
            || $report->status !== VisitReportStatus::Pending
            || $visit->status !== VisitStatus::WAITING_REPORT) {
            throw ValidationException::withMessages([
                'report' => 'Laporan ini tidak dapat diproses atau sudah pernah diputuskan.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function visitSnapshot(Visit $visit): array
    {
        $destinationNames = $visit->destinations()->orderBy('units.nama_unit_kerja')->pluck('units.nama_unit_kerja')->all();

        if ($destinationNames === []) {
            $destinationNames = [$visit->destinationUnit()->value('nama_unit_kerja')];
        }

        return [
            'Nama agenda' => $visit->title,
            'Nomor surat' => $visit->letter_number,
            'Lokasi tujuan' => array_values(array_filter($destinationNames)),
            'Tanggal mulai' => $visit->start_at?->format('Y-m-d H:i:s'),
            'Tanggal selesai' => $visit->end_at?->format('Y-m-d H:i:s'),
            'PIC unit kerja' => $visit->picUnitKerja()->value('nama_unit'),
            'Peserta' => $visit->participants()->orderBy('employees.name')->pluck('employees.name')->all(),
            'Keperluan' => $visit->purpose,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changedVisitData(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $label => $value) {
            if (($before[$label] ?? null) !== $value) {
                $changes[$label] = [
                    'from' => $before[$label] ?? null,
                    'to' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function log(
        Visit $visit,
        ?VisitStatus $from,
        VisitStatus $to,
        ?int $actorId,
        string $notes,
        array $changes = [],
    ): void {
        VisitStatusLog::query()->create([
            'visit_id' => $visit->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actorId,
            'notes' => $notes,
            'changes' => $changes === [] ? null : $changes,
            'created_at' => now(),
        ]);
    }
}
