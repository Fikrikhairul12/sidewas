<?php

namespace App\Services\Kunjungan;

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

            return $visit->refresh();
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

            if ($lockedVisit->reports()->exists()) {
                throw ValidationException::withMessages([
                    'report' => 'Laporan untuk kunjungan ini sudah pernah diunggah.',
                ]);
            }

            $storedName = $lockedVisit->visit_number.'-'.Str::uuid().'.pdf';
            $path = $file->storeAs('dokumen/laporan-kunjungan', $storedName, 'public');
            $report = $visit->reports()->create([
                'version' => 1,
                'original_filename' => Str::limit(basename($file->getClientOriginalName()), 240, ''),
                'stored_filename' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'file_size' => $file->getSize(),
                'uploaded_by_user_id' => $actor->id,
                'is_current' => true,
                'uploaded_at' => now(),
            ]);

            $from = $lockedVisit->status;
            $lockedVisit->update(['status' => VisitStatus::COMPLETED]);
            $this->log($lockedVisit, $from, VisitStatus::COMPLETED, $actor->id, 'Laporan PDF kunjungan diunggah.');

            return $report;
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

    private function log(Visit $visit, ?VisitStatus $from, VisitStatus $to, ?int $actorId, string $notes): void
    {
        VisitStatusLog::query()->create([
            'visit_id' => $visit->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actorId,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
