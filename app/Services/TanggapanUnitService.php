<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Enums\SuratStatus;
use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SnpTanggapan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TanggapanUnitService
{
    public function module(SnpButir|DjsnButir $butir): string
    {
        return $butir instanceof SnpButir ? 'snp' : 'djsn';
    }

    public function currentRoundId(SnpButir|DjsnButir $butir): ?int
    {
        return $butir->record?->putaran_aktif_id
            ?? $butir->record?->putaran->firstWhere('nomor_putaran', 0)?->id;
    }

    public function canRepresent(User $user, SnpButir|DjsnButir $butir, ?int $unitId): bool
    {
        if ($user->isSuperAdmin() || ($butir instanceof SnpButir && $user->hasRoleType('admin_snp'))) {
            return true;
        }

        $allowed = $butir instanceof SnpButir
            ? $user->canCreateSnpTanggapanForButir($butir)
            : $user->canCreateDjsnTanggapanForButir($butir);

        return $allowed && $unitId !== null
            && in_array($unitId, array_map('intval', $user->unitKerjaIds()), true);
    }

    public function availablePics(SnpButir|DjsnButir $butir, User $user): Collection
    {
        if (! $this->roundIsOpen($butir)) {
            return collect();
        }

        $answeredUnits = $butir->tanggapanAktif->pluck('unit_kerja_id')->filter()->map(fn ($id): int => (int) $id);

        return $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')
            ->unique('unit_kerja_id')
            ->filter(fn ($pic): bool => $this->canRepresent($user, $butir, (int) $pic->unit_kerja_id)
                && ! $answeredUnits->contains((int) $pic->unit_kerja_id))
            ->values();
    }

    public function canEdit(User $user, SnpTanggapan|DjsnTanggapan $response): bool
    {
        $butir = $response->butir;

        return $butir !== null && $this->roundIsOpen($butir)
            && (int) $response->putaran_id === (int) $this->currentRoundId($butir)
            && ! $this->hasReviewedMaterial($butir)
            && $this->canRepresent($user, $butir, $response->unit_kerja_id === null ? null : (int) $response->unit_kerja_id);
    }

    public function hasReviewedMaterial(SnpButir|DjsnButir $butir): bool
    {
        if ($this->isClosed($butir)) {
            return true;
        }

        return $butir->reviews->where('tahap_review', 'tanggapan')
            ->filter(fn ($review): bool => (int) $review->putaran_id === (int) $this->currentRoundId($butir))
            ->contains(fn ($review): bool => filled($review->hasil_review) || $review->status !== 'belum_ditanggapi');
    }

    /** @return array<string, mixed> */
    public function presentation(SnpButir|DjsnButir $butir, User $user): array
    {
        $module = $this->module($butir);
        $responses = $butir->tanggapanAktif;
        foreach ($responses as $response) {
            $response->setRelation('butir', $butir);
        }
        $pics = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')->unique('unit_kerja_id');
        $round = $butir->record?->putaran->firstWhere('id', $this->currentRoundId($butir));
        $label = $round === null || $round->isLegacy() ? 'Putaran awal' : 'Putaran '.$round->nomor_putaran;
        $cards = $this->responseCards($butir, $user, $responses, true);

        return [
            'responses' => $responses,
            'available_pics' => $this->availablePics($butir, $user),
            'complete' => $pics->isNotEmpty() && $pics->every(fn ($pic): bool => $responses->contains(fn ($response): bool => (int) $response->unit_kerja_id === (int) $pic->unit_kerja_id)),
            'round_id' => $this->currentRoundId($butir),
            'round_label' => $label,
            'is_closed' => $butir->record?->getAttribute('closed_at') !== null,
            'detail' => [
                'id' => $butir->id,
                'id_surat' => $butir->record?->{'id_'.$module},
                'id_butir' => $butir->{'id_butir_'.$module},
                'butir_content' => $butir->{'butir_'.$module},
                'round_label' => $label,
                'closed_at' => $butir->record?->closed_at?->format('d/m/Y H:i'),
                'closed_status_label' => $this->isClosed($butir) ? SuratStatus::labelFor($butir->record->status) : null,
                'pic_tanggapans' => $cards->values()->all(),
                'rounds' => $this->detailRounds($butir, $user, $cards),
            ],
        ];
    }

    private function responseCards(SnpButir|DjsnButir $butir, User $user, Collection $responses, bool $active): Collection
    {
        $module = $this->module($butir);
        foreach ($responses as $response) {
            $response->setRelation('butir', $butir);
        }
        $pics = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')->unique('unit_kerja_id');
        $cards = $responses->map(function ($response) use ($butir, $user, $module, $active): array {
            $pic = $butir->butirPics->firstWhere('id', $response->butir_pic_id);

            return [
                'id' => 'tanggapan-'.$response->id,
                'tanggapan_id' => $response->id,
                'update_url' => route($module.'.tanggapan.update', $response),
                'can_edit' => $active && $this->canEdit($user, $response),
                'unit_label' => $response->unitKerja ? $response->unitKerja->kode_unit.' - '.$response->unitKerja->nama_unit : 'Unit tidak tercatat (data lama)',
                'initial' => $response->unitKerja?->kode_unit ?? '-',
                'jenis_pic' => $pic ? ($pic->jenis_pic === 'utama' ? 'PIC Utama' : 'PIC Pendukung') : 'Data lama',
                'id_butir' => $butir->{'id_butir_'.$module},
                'butir_content' => $butir->{'butir_'.$module},
                'sudah_menanggapi' => true,
                'submitted_after_close' => $response->submitted_after_close_at !== null,
                'submitted_after_close_at' => $response->submitted_after_close_at?->format('d/m/Y H:i'),
                'tanggapan' => $response->tanggapan,
                'tanggapan_singkat' => Str::limit($response->tanggapan ?? '', 110),
                'deliverables' => $response->deliverables,
                'dokumen_url' => $response->dokumen ? asset('storage/'.$response->dokumen) : null,
                'ubah_tgl' => $response->ubah_tgl,
                'creator' => $response->creator?->name ?? '-',
                'created_at' => $response->created_at?->format('d/m/Y H:i') ?? '-',
                'updater' => $response->updater?->name ?? '-',
                'updated_at' => $response->updated_at?->format('d/m/Y H:i') ?? '-',
            ];
        });

        foreach ($pics as $pic) {
            if ($active && ! $responses->contains(fn ($response): bool => (int) $response->unit_kerja_id === (int) $pic->unit_kerja_id)) {
                $cards->push([
                    'id' => 'unit-'.$pic->unit_kerja_id,
                    'can_edit' => false,
                    'unit_label' => ($pic->unitKerja?->kode_unit ?? '-').' - '.($pic->unitKerja?->nama_unit ?? '-'),
                    'initial' => $pic->unitKerja?->kode_unit ?? '-',
                    'jenis_pic' => $pic->jenis_pic === 'utama' ? 'PIC Utama' : 'PIC Pendukung',
                    'id_butir' => $butir->{'id_butir_'.$module},
                    'sudah_menanggapi' => false,
                ]);
            }
        }

        return $cards;
    }

    /** @return list<array<string, mixed>> */
    private function detailRounds(SnpButir|DjsnButir $butir, User $user, Collection $activeCards): array
    {
        if (! ReviuPutaranService::enabled() || $butir->record === null) {
            return [];
        }
        $module = $this->module($butir);
        $canReadCompilations = $user->{'canAccess'.ucfirst($module).'Kompilasi'}();
        $canReadReviews = $user->{'canAccess'.ucfirst($module).'Review'}();
        $superAdmin = $user->isSuperAdmin();
        $komiteIds = $user->komiteIds();

        return $butir->record->putaran->sortByDesc('nomor_putaran')->map(function ($round) use ($butir, $user, $activeCards, $module, $canReadCompilations, $canReadReviews, $superAdmin, $komiteIds): array {
            $active = (int) $round->id === $this->currentRoundId($butir) && $round->archived_at === null;
            $inRound = fn ($row): bool => (int) $row->putaran_id === (int) $round->id || ($round->isLegacy() && $row->putaran_id === null);
            $cards = $active ? $activeCards : $this->responseCards($butir, $user, $butir->tanggapanUnit->filter($inRound), false);
            $compilations = $canReadCompilations ? $butir->kompilasis->filter($inRound) : collect();
            $reviews = $canReadReviews ? $butir->reviews->filter(fn ($row): bool => $inRound($row) && ($superAdmin || in_array($row->komite_id, $komiteIds))) : collect();

            return [
                'id' => $round->id,
                'round_label' => $round->isLegacy() ? 'Putaran awal' : 'Putaran '.$round->nomor_putaran,
                'is_current' => $active,
                'read_only' => ! $active,
                'is_closed' => $active && $butir->record->closed_at !== null,
                'opened_at' => $round->opened_at?->format('d/m/Y H:i'),
                'archived_at' => $round->archived_at?->format('d/m/Y H:i'),
                'pic_tanggapans' => $cards->values()->all(),
                'compilations' => $compilations->map(fn ($row): array => [
                    'id' => $row->id, 'content' => $row->hasil_kompilasi, 'deliverables' => $row->deliverables,
                    'creator' => $row->creator?->name ?? '-', 'created_at' => $row->created_at?->format('d/m/Y H:i') ?? '-',
                    'document_url' => $row->dokumen && $user->{'canAccess'.ucfirst($module).'Kompilasi'}() ? route($module.'.kompilasi.dokumen', $row) : null,
                ])->values()->all(),
                'reviews' => $reviews->map(fn ($row): array => [
                    'id' => $row->id, 'content' => $row->hasil_review, 'deliverables' => $row->deliverables,
                    'status_label' => ReviuStatus::labelFor($row->status),
                    'creator' => $row->updater?->name ?? $row->creator?->name ?? '-', 'created_at' => $row->updated_at?->format('d/m/Y H:i') ?? '-',
                    'document_url' => $row->dokumen && $user->{'canReview'.ucfirst($module).'ByKomite'}($row->komite_id) ? route($module.'.reviu.hasil-dokumen', $row) : null,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** @param array<string, mixed> $data */
    public function create(SnpButir|DjsnButir $butir, User $user, array $data, Request $request): SnpTanggapan|DjsnTanggapan
    {
        $module = $this->module($butir);
        $newFile = null;

        try {
            return $butir->getConnection()->transaction(function () use ($butir, $user, $data, $request, $module, &$newFile): SnpTanggapan|DjsnTanggapan {
                $this->lockContext($butir);
                $expectedRound = $data['putaran_id'] ?? null;

                if (! $this->roundIsOpen($butir)
                    || ($expectedRound !== null && (int) $expectedRound !== (int) $this->currentRoundId($butir))
                    || ($butir->record->putaran_aktif_id !== null && (int) $expectedRound !== (int) $butir->record->putaran_aktif_id)) {
                    throw ValidationException::withMessages(['putaran_id' => 'Putaran telah berubah atau dikunci. Muat ulang halaman untuk mengisi putaran yang aktif.']);
                }

                $pic = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->firstWhere('id', $data['butir_pic_id']);
                if (! $pic || $pic->unit_kerja_id === null) {
                    throw ValidationException::withMessages(['butir_pic_id' => 'PIC unit tidak sesuai dengan butir ini.']);
                }
                abort_unless($this->canRepresent($user, $butir, (int) $pic->unit_kerja_id), 403, 'Anda tidak mewakili unit kerja yang dipilih.');

                if ($butir->tanggapanAktif()->where('unit_kerja_id', $pic->unit_kerja_id)->lockForUpdate()->first(['tb_tanggapan.id']) !== null) {
                    throw ValidationException::withMessages(['butir_pic_id' => 'Unit kerja ini sudah memberi tanggapan pada putaran ini. Edit tanggapan melalui Detail.']);
                }

                $roundId = $this->currentRoundId($butir) ?? app(SuratPutaranService::class)->legacyFor($butir->getConnectionName(), $butir->record->getRawOriginal())->id;
                $newFile = $request->file('dokumen')?->store('dokumen/tanggapan-'.$module, 'public');
                $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
                $response = $class::create([
                    'id_butir_'.$module => $butir->{'id_butir_'.$module},
                    'putaran_id' => $roundId,
                    'unit_kerja_id' => $pic->unit_kerja_id,
                    'butir_pic_id' => $pic->id,
                    'tanggapan' => $data['tanggapan'],
                    'deliverables' => $data['deliverables'],
                    'dokumen' => $newFile,
                    ...($module === 'djsn' ? ['ubah_tgl' => $data['ubah_tgl'] ?? null, 'status_pengajuan_tgl' => 'pending'] : []),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->prepareLegacyNextStep($butir, $response, $user);
                $this->log($request, $user, $response, 'create');

                return $response;
            });
        } catch (Throwable $exception) {
            if ($newFile) {
                Storage::disk('public')->delete($newFile);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(SnpTanggapan|DjsnTanggapan $response, User $user, array $data, Request $request): void
    {
        $newFile = null;
        $oldFile = null;

        try {
            $response->getConnection()->transaction(function () use ($response, $user, $data, $request, &$newFile, &$oldFile): void {
                $butir = $response->butir;
                $this->lockContext($butir);
                $current = $response->newQuery()->lockForUpdate()->findOrFail($response->id);
                $response->setRawAttributes($current->getAttributes(), true)->setRelations([])->setRelation('butir', $butir);
                abort_unless($this->canEdit($user, $response), 403, 'Tanggapan tidak dapat diubah: periksa unit, putaran, dan penguncian reviu.');
                $oldValues = $response->toArray();
                $oldFile = $response->dokumen;
                $module = $this->module($response->butir);
                $newFile = $request->file('dokumen')?->store('dokumen/tanggapan-'.$module, 'public');
                $requestedDate = $data['ubah_tgl'] ?? (array_key_exists('ubah_tgl', $data) ? null : $response->ubah_tgl);

                $response->update([
                    'tanggapan' => $data['tanggapan'],
                    'deliverables' => $data['deliverables'],
                    'dokumen' => $newFile ?? $oldFile,
                    ...($module === 'djsn' ? [
                        'ubah_tgl' => $requestedDate,
                        'status_pengajuan_tgl' => $requestedDate != $response->ubah_tgl ? 'pending' : $response->status_pengajuan_tgl,
                    ] : []),
                    'updated_by' => $user->id,
                ]);
                $this->log($request, $user, $response, 'update', $oldValues);
            });
        } catch (Throwable $exception) {
            if ($newFile) {
                Storage::disk('public')->delete($newFile);
            }
            throw $exception;
        }

        if ($newFile && $oldFile) {
            Storage::disk('public')->delete($oldFile);
        }
    }

    private function lockContext(SnpButir|DjsnButir $butir): void
    {
        $record = $butir->record()->lockForUpdate()->firstOrFail();
        $record->setRelation('putaran', $record->putaran()->lockForUpdate()->get());
        $butir->refresh()->setRelation('record', $record);
        $butir->setRelation('butirPics', $butir->butirPics()->lockForUpdate()->get());
        $butir->setRelation('reviews', $butir->reviews()->lockForUpdate()->get());
    }

    public function applyStatusFilter(Builder $query, User $user, string $status): void
    {
        if (! in_array($status, ['sudah', 'belum'], true)) {
            return;
        }
        $module = $query->getModel() instanceof SnpButir ? 'snp' : 'djsn';
        $allUnits = $user->isSuperAdmin() || $user->hasRoleType('admin_'.$module);
        $unitIds = $user->unitKerjaIds();
        $filter = function (Builder $responses) use ($allUnits, $unitIds): void {
            if (! $allUnits) {
                $responses->whereIn('unit_kerja_id', $unitIds);
            }
        };
        if ($status === 'sudah') {
            $query->whereHas('tanggapanAktif', $filter);
        } else {
            $query->whereDoesntHave('tanggapanAktif', $filter);
        }
    }

    private function roundIsOpen(SnpButir|DjsnButir $butir): bool
    {
        if ($butir->record === null) {
            return false;
        }
        $round = $butir->record->putaran->firstWhere('id', $this->currentRoundId($butir));

        if ($butir->record->closed_at !== null && (int) $butir->record->closed_putaran_id !== (int) $round?->id) {
            return false;
        }

        return $round === null || $round->archived_at === null;
    }

    private function isClosed(SnpButir|DjsnButir $butir): bool
    {
        return $butir->record?->status === 'tuntas'
            || ($butir->record?->isWorkflowClosed() ?? false);
    }

    private function prepareLegacyNextStep(SnpButir|DjsnButir $butir, SnpTanggapan|DjsnTanggapan $response, User $user): void
    {
        if (ReviuPutaranService::enabled()) {
            return;
        }
        if ($this->hasReviewedMaterial($butir)) {
            return;
        }
        if ($butir instanceof DjsnButir) {
            $attributes = ['id_butir_djsn' => $butir->id_butir_djsn, 'putaran_id' => $response->putaran_id, 'tahap_review' => 'tanggapan'];
            DjsnReview::firstOrCreate($attributes, [
                'id_tanggapan' => $response->id,
                'komite_id' => $butir->butirPics->firstWhere('jenis_pic', 'komite')?->komite_id,
                'status' => 'belum_ditanggapi',
                'created_by' => $user->id,
            ]);

            return;
        }

        $unitIds = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->pluck('unit_kerja_id')->filter()->unique();
        $answered = $butir->tanggapanAktif()->pluck('unit_kerja_id')->filter()->unique();
        if ($unitIds->isNotEmpty() && $unitIds->diff($answered)->isEmpty()) {
            SnpKompilasi::firstOrCreate([
                'id_butir_snp' => $butir->id_butir_snp,
                'putaran_id' => $response->putaran_id,
                'tahap_kompilasi' => 'tanggapan',
            ], ['status' => 'belum_dikompilasi', 'created_by' => $user->id]);
        }
    }

    /** @param array<string, mixed>|null $oldValues */
    private function log(Request $request, User $user, SnpTanggapan|DjsnTanggapan $response, string $action, ?array $oldValues = null): void
    {
        $module = $this->module($response->butir);
        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => $module,
            'database_name' => 'sidewas_'.$module,
            'table_name' => 'tb_tanggapan',
            'record_key' => $response->{'id_butir_'.$module},
            'action' => $action,
            'description' => ($response->submitted_after_close_at !== null ? 'Tanggapan setelah penutupan: ' : '')
                .($action === 'create' ? 'User membuat' : 'User mengubah').' tanggapan '.strtoupper($module).'.',
            'old_values' => $oldValues,
            'new_values' => $response->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
