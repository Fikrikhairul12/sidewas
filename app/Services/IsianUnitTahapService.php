<?php

namespace App\Services;

use App\Enums\SuratStatus;
use App\Enums\TahapProses;
use App\Models\DjsnButir;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SuratTahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

class IsianUnitTahapService
{
    public static function enabled(): bool
    {
        return (bool) config('sidewas.alur_revisi_enabled', false);
    }

    public static function isInput(Model $model): bool
    {
        return in_array($model->getConnectionName(), ['mysql_snp', 'mysql_djsn'], true)
            && in_array($model->getTable(), ['tb_tanggapan', 'tb_tindak_lanjut'], true);
    }

    public function module(Model $model): string
    {
        return SuratTahap::moduleFor($model->getConnectionName());
    }

    public function canRepresent(User $user, SnpButir|DjsnButir $butir, TahapProses $type, ?int $unit): bool
    {
        $module = $this->module($butir);
        if ($user->isSuperAdmin() || $module === 'snp' && $type === TahapProses::TanggapanAwal && $user->hasRoleType('admin_snp')) {
            return true;
        }
        $permission = 'canCreate'.ucfirst($module).($type === TahapProses::TanggapanAwal ? 'Tanggapan' : 'TindakLanjut').'ForButir';

        return $unit !== null && $user->{$permission}($butir) && in_array($unit, array_map('intval', $user->unitKerjaIds()), true);
    }

    public function sourceUnit(Model $source, SnpButir|DjsnButir $butir): ?int
    {
        $pic = $butir->butirPics->firstWhere('id', $source->getAttribute('butir_pic_id'));
        $unit = $source->getAttribute('unit_kerja_id');
        if ($source->getAttribute('butir_pic_id') !== null && $pic === null) {
            return null;
        }
        if ($unit !== null && $pic !== null && (int) $pic->unit_kerja_id !== (int) $unit) {
            return null;
        }

        return $unit !== null ? (int) $unit : ($pic?->unit_kerja_id === null ? null : (int) $pic->unit_kerja_id);
    }

    public function sources(SnpButir|DjsnButir $butir, SuratTahap $stage): Collection
    {
        return $butir->tanggapanUnit->concat($butir->tindakLanjuts)
            ->filter(fn ($source): bool => (int) $source->tahap_id === (int) $stage->id)
            ->sortBy('id')->values();
    }

    private function hasUnmappedInputs(SnpButir|DjsnButir $butir): bool
    {
        return $butir->tanggapanUnit->concat($butir->tindakLanjuts)->contains(fn ($source): bool => $source->tahap_id === null);
    }

    private function hasUnknownUnits(SnpButir|DjsnButir $butir, SuratTahap $stage): bool
    {
        return $this->sources($butir, $stage)->contains(fn ($source): bool => $this->sourceUnit($source, $butir) === null);
    }

    private function canStartInitial(SnpButir|DjsnButir $butir): bool
    {
        $record = $butir->record;
        $initial = $record?->tahapanProses->first(fn ($stage): bool => $stage->tahap_proses === TahapProses::TanggapanAwal);

        return $record !== null && ! $record->isWorkflowClosed() && $record->status !== 'tuntas'
            && $record->tahap_aktif_id === null && $record->putaran_aktif_id === null
            && ! $record->putaran->contains(fn ($round): bool => $round->nomor_putaran > 0)
            && ! $record->tahapanProses->contains(fn ($stage): bool => $stage->tahap_proses === TahapProses::TindakLanjut)
            && ($initial === null || $initial->status_pemetaan === 'terpetakan' && $initial->archived_at === null && ! $this->hasUnknownUnits($butir, $initial))
            && ! $this->hasUnmappedInputs($butir);
    }

    private function stageIsCurrent(SnpButir|DjsnButir $butir, SuratTahap $stage): bool
    {
        $record = $butir->record;

        return $record !== null && (int) $record->tahap_aktif_id === (int) $stage->id
            && $stage->archived_at === null && $stage->nomor_putaran !== null && $stage->status_pemetaan === 'terpetakan'
            && ! $this->hasUnknownUnits($butir, $stage)
            && ! $this->hasUnmappedInputs($butir);
    }

    private function hasDecision(SnpButir|DjsnButir $butir, SuratTahap $stage): bool
    {
        return $butir->reviews->where('tahap_id', $stage->id)
            ->contains(fn ($review): bool => filled($review->hasil_review) || $review->status !== 'belum_ditanggapi');
    }

    private function hasCompilation(SnpButir|DjsnButir $butir, SuratTahap $stage): bool
    {
        return $butir->kompilasis->where('tahap_id', $stage->id)
            ->contains(fn ($row): bool => filled($row->hasil_kompilasi) || $row->status !== 'belum_dikompilasi');
    }

    public function canEdit(User $user, Model $source, SnpButir|DjsnButir $butir, SuratTahap $stage): bool
    {
        $unit = $this->sourceUnit($source, $butir);

        $current = $this->stageIsCurrent($butir, $stage) || $stage->tahap_proses === TahapProses::TanggapanAwal && $this->canStartInitial($butir);

        return $unit !== null && $current && ! $butir->record->isWorkflowClosed()
            && ! $this->hasDecision($butir, $stage)
            && $this->sources($butir, $stage)->filter(fn ($row): bool => $this->sourceUnit($row, $butir) === $unit)->count() === 1
            && $this->canRepresent($user, $butir, $stage->tahap_proses, $unit);
    }

    public function presentation(SnpButir|DjsnButir $butir, User $user, TahapProses $type): array
    {
        $module = $this->module($butir);
        $stages = $butir->record->tahapanProses->filter(fn ($stage): bool => $stage->tahap_proses === $type)
            ->sortByDesc(fn ($stage): int => $stage->nomor_putaran ?? -1)->values();
        $active = $stages->firstWhere('id', $butir->record->tahap_aktif_id);
        $initial = $type === TahapProses::TanggapanAwal && $this->canStartInitial($butir);
        $stage = $active ?? $stages->first();
        $current = $stage !== null && $this->stageIsCurrent($butir, $stage);
        $open = $current || $initial;
        if ($butir->record->isWorkflowClosed() && ($butir->record->closed_at === null
            || (int) $butir->record->closed_putaran_id !== (int) ($stage?->putaran_asal_id ?? $butir->record->putaran_aktif_id
                ?? $butir->record->putaran->firstWhere('nomor_putaran', 0)?->id))) {
            $open = false;
        }
        $answered = $stage === null ? collect() : $this->sources($butir, $stage);
        $pics = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')->unique('unit_kerja_id');
        $available = $open ? $pics->filter(fn ($pic): bool => $this->canRepresent($user, $butir, $type, (int) $pic->unit_kerja_id)
            && ! $answered->contains(fn ($row): bool => $this->sourceUnit($row, $butir) === (int) $pic->unit_kerja_id))->values() : collect();
        $history = $stages->map(fn ($item): array => $this->stagePresentation($butir, $user, $item))->all();
        if ($history === [] && $type === TahapProses::TanggapanAwal) {
            $history[] = ['id' => null, 'label' => 'Tanggapan Awal', 'is_current' => $open, 'read_only' => ! $open,
                'needs_reconciliation' => false, 'items' => [], 'missing_units' => $pics->map(fn ($pic) => $pic->unitKerja?->nama_unit ?? 'Unit #'.$pic->unit_kerja_id)->values()->all()];
        }

        return [
            'stage_id' => $stage?->id, 'available_pics' => $available, 'has_answer' => $answered->isNotEmpty(),
            'is_current' => $open, 'closed' => $butir->record->isWorkflowClosed(),
            'needs_reconciliation' => $this->hasUnmappedInputs($butir) || $stage?->status_pemetaan === 'perlu_rekonsiliasi'
                || $stage !== null && $this->hasUnknownUnits($butir, $stage),
            'history' => $history,
            'detail' => ['butir_id' => $butir->id, 'butir_key' => $butir->{'id_butir_'.$module},
                'butir_content' => $butir->{'butir_'.$module}, 'nomor_surat' => $butir->record->nomor_surat,
                'type_label' => $type->label(), 'stages' => $history,
                'closed_label' => $butir->record->isWorkflowClosed() ? SuratStatus::labelFor($butir->record->status) : null],
        ];
    }

    private function stagePresentation(SnpButir|DjsnButir $butir, User $user, SuratTahap $stage): array
    {
        $module = $this->module($butir);
        $sources = $this->sources($butir, $stage);
        $items = $sources->map(function ($source) use ($butir, $user, $stage, $module): array {
            $unit = $this->sourceUnit($source, $butir);
            $pic = $butir->butirPics->firstWhere('unit_kerja_id', $unit);
            $isResponse = $source->getTable() === 'tb_tanggapan';
            $afterClose = $source->getAttribute('submitted_after_close_at') !== null;
            $afterCompilation = $source->getAttribute('submitted_after_compilation_at') !== null;

            return [
                'key' => $source->getTable().':'.$source->id, 'id' => $source->id,
                'unit_id' => $unit,
                'unit_label' => $source->unitKerja?->nama_unit ?? $pic?->unitKerja?->nama_unit ?? 'Unit tidak tercatat (data lama)',
                'isi' => $isResponse ? $source->tanggapan : $source->tindak_lanjut,
                'deliverables' => $source->deliverables,
                'document_url' => $source->dokumen ? asset('storage/'.$source->dokumen) : null,
                'creator' => $source->creator?->name ?? '-', 'created_at' => $source->created_at?->format('d/m/Y H:i') ?? '-',
                'updater' => $source->updater?->name ?? '-', 'updated_at' => $source->updated_at?->format('d/m/Y H:i') ?? '-',
                'can_edit' => $this->canEdit($user, $source, $butir, $stage),
                'update_url' => route($module.($isResponse ? '.tanggapan.update' : '.tindak-lanjut.update'), $source->id),
                'after_close' => $afterClose, 'not_compiled' => $afterClose || $afterCompilation,
                'changed_after_compilation' => $source->getAttribute('changed_after_compilation_at') !== null,
            ];
        });
        $units = $stage->penugasan_unit[$butir->{'id_butir_'.$module}] ?? null;
        $missing = $units === null ? $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit_kerja_id')
            ->unique('unit_kerja_id')->map(fn ($pic): array => ['id' => (int) $pic->unit_kerja_id, 'name' => $pic->unitKerja?->nama_unit ?? 'Unit #'.$pic->unit_kerja_id]) : collect($units);

        $current = $this->stageIsCurrent($butir, $stage) || $stage->tahap_proses === TahapProses::TanggapanAwal && $this->canStartInitial($butir);

        return [
            'id' => $stage->id, 'label' => $stage->tahap_proses->label().($stage->tahap_proses === TahapProses::TindakLanjut
                ? ($stage->nomor_putaran === null ? ' (nomor belum terverifikasi)' : ' Putaran '.$stage->nomor_putaran) : ''),
            'is_current' => $current,
            'is_closed' => $current && $butir->record->isWorkflowClosed(),
            'read_only' => ! $current || $butir->record->isWorkflowClosed(),
            'needs_reconciliation' => $stage->status_pemetaan !== 'terpetakan' || $this->hasUnknownUnits($butir, $stage),
            'items' => $items->all(),
            'missing_units' => $missing->reject(fn ($unit): bool => $sources->contains(fn ($row): bool => $this->sourceUnit($row, $butir) === (int) $unit['id']))->pluck('name')->values()->all(),
        ];
    }

    private function lockButir(SnpButir|DjsnButir $butir): void
    {
        $record = $butir->record()->lockForUpdate()->firstOrFail();
        $record->setRelation('tahapAktif', $record->tahapAktif()->lockForUpdate()->first());
        $record->setRelation('tahapanProses', $record->tahapanProses()->lockForUpdate()->get());
        $record->setRelation('putaran', $record->putaran()->lockForUpdate()->get());
        $butir->refresh()->setRelation('record', $record);
        $this->loadLockedInputs($butir);
    }

    private function loadLockedInputs(SnpButir|DjsnButir $butir): void
    {
        foreach (['butirPics', 'tanggapanUnit', 'tindakLanjuts', 'reviews', 'kompilasis'] as $relation) {
            $butir->setRelation($relation, $butir->{$relation}()->lockForUpdate()->get());
        }
    }

    public function create(SnpButir|DjsnButir $butir, User $user, TahapProses $type, array $data, Request $request): Model
    {
        $newFile = null;
        try {
            return $butir->getConnection()->transaction(function () use ($butir, $user, $type, $data, $request, &$newFile): Model {
                $this->lockButir($butir);
                $record = $butir->record;
                $stage = $record->tahapAktif;
                if ($stage === null && $type === TahapProses::TanggapanAwal && $this->canStartInitial($butir)) {
                    $round = app(SuratPutaranService::class)->legacyFor($butir->getConnectionName(), $record->getRawOriginal());
                    $stage = $record->tahapanProses()->firstOrCreate(['tahap_proses' => $type->value, 'nomor_putaran' => 0], [
                        'kunci_asal' => 'tanggapan_awal', 'putaran_asal_id' => $round->id, 'asal_pemetaan' => 'aplikasi',
                        'status_pemetaan' => 'terpetakan', 'created_by' => $user->id,
                    ]);
                    $record->forceFill(['tahap_aktif_id' => $stage->id])->save();
                    $record->setRelation('tahapAktif', $stage);
                }
                if ($stage === null || $stage->tahap_proses !== $type || ! $this->stageIsCurrent($butir, $stage)
                    || isset($data['tahap_id']) && (int) $data['tahap_id'] !== (int) $stage->id
                    || $type === TahapProses::TindakLanjut && empty($data['tahap_id'])) {
                    throw ValidationException::withMessages(['tahap_id' => 'Tahap telah berubah, terkunci, atau perlu rekonsiliasi. Perbaikan diisi melalui Tindak Lanjut pada putaran aktif.']);
                }
                $pic = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->firstWhere('id', $data['butir_pic_id']);
                if ($pic === null || $pic->unit_kerja_id === null) {
                    throw ValidationException::withMessages(['butir_pic_id' => 'PIC unit tidak sesuai dengan butir ini.']);
                }
                abort_unless($this->canRepresent($user, $butir, $type, (int) $pic->unit_kerja_id), 403);
                $module = $this->module($butir);
                $class = $stage->relatedClass($type === TahapProses::TanggapanAwal ? 'Tanggapan' : 'TindakLanjut');
                $newFile = $request->file('dokumen')?->store('dokumen/'.($type === TahapProses::TanggapanAwal ? 'tanggapan-' : 'tindak-lanjut-').$module, 'public');
                $model = new $class([
                    'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tahap_id' => $stage->id,
                    'putaran_id' => $stage->putaran_asal_id ?? $record->putaran_aktif_id ?? app(SuratPutaranService::class)->legacyFor($butir->getConnectionName(), $record->getRawOriginal())->id,
                    'unit_kerja_id' => $pic->unit_kerja_id, 'butir_pic_id' => $pic->id,
                    $type === TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut' => $data['isi'],
                    'deliverables' => $data['deliverables'], 'dokumen' => $newFile, 'created_by' => $user->id, 'updated_by' => $user->id,
                    ...($type === TahapProses::TindakLanjut ? ['putaran_tl' => $stage->nomor_putaran] : []),
                ]);
                $model->save();
                $this->log($request, $user, $model, 'create_isian_tahap');

                return $model;
            });
        } catch (Throwable $exception) {
            if ($newFile !== null) {
                Storage::disk('public')->delete($newFile);
            }
            if ($exception instanceof LogicException) {
                throw ValidationException::withMessages(['butir_pic_id' => $exception->getMessage()]);
            }
            throw $exception;
        }
    }

    public function update(Model $source, User $user, array $data, Request $request): void
    {
        $newFile = null;
        try {
            $source->getConnection()->transaction(function () use ($source, $user, $data, $request, &$newFile): void {
                $butir = $source->butir;
                abort_unless($butir, 422);
                $this->lockButir($butir);
                $source->setRawAttributes($source->newQuery()->lockForUpdate()->findOrFail($source->id)->getAttributes(), true)->setRelations([]);
                $stage = $source->tahapProses;
                if ($stage?->tahap_proses === TahapProses::TanggapanAwal && $this->canStartInitial($butir)) {
                    $butir->record->forceFill(['tahap_aktif_id' => $stage->id])->save();
                }
                abort_unless($stage && $this->canEdit($user, $source, $butir, $stage), 403, 'Isian terkunci atau unit pengisi tidak sesuai.');
                $old = $source->getRawOriginal();
                $newFile = $request->file('dokumen')?->store('dokumen/isian-'.$this->module($source), 'public');
                $source->fill([
                    $source->getTable() === 'tb_tanggapan' ? 'tanggapan' : 'tindak_lanjut' => $data['isi'],
                    'deliverables' => $data['deliverables'], 'dokumen' => $newFile ?? $source->dokumen, 'updated_by' => $user->id,
                ])->save();
                $this->log($request, $user, $source, 'update_isian_tahap', $old);
            });
        } catch (Throwable $exception) {
            if ($newFile !== null) {
                Storage::disk('public')->delete($newFile);
            }
            throw $exception;
        }
    }

    public function prepareForSave(Model $source, object $record): void
    {
        $module = $this->module($source);
        $column = 'id_butir_'.$module;
        $butir = $source->butir()->firstOrFail();
        $butir->setRelation('record', $butir->record()->lockForUpdate()->firstOrFail());
        $this->loadLockedInputs($butir);
        $stage = SuratTahap::on($source->getConnectionName())->lockForUpdate()->find($source->tahap_id);
        if ($stage === null || (int) $stage->record_id !== (int) $record->id || ! $this->stageIsCurrent($butir, $stage)) {
            throw new LogicException('Isian hanya dapat disimpan pada tahap dan putaran aktif yang sesuai.');
        }
        if (! $source->exists && ($source->getTable() === 'tb_tanggapan') !== ($stage->tahap_proses === TahapProses::TanggapanAwal)) {
            throw new LogicException('Tanggapan awal hanya sekali; perbaikan harus disimpan sebagai Tindak Lanjut.');
        }
        if ($source->exists && ($source->isDirty([$column, 'tahap_id', 'putaran_id', 'putaran_tl', 'butir_pic_id', 'created_by', 'created_at', 'submitted_after_close_at', 'submitted_after_compilation_at', 'changed_after_compilation_at'])
            || $source->getRawOriginal('unit_kerja_id') !== null && $source->isDirty('unit_kerja_id'))) {
            throw new LogicException('Identitas sumber, unit, waktu awal, dan penanda isian tidak boleh diganti.');
        }
        if (! $source->exists && $source->getTable() === 'tb_tindak_lanjut'
            && (int) $source->putaran_tl !== $stage->nomor_putaran) {
            throw new LogicException('Nomor putaran Tindak Lanjut harus sesuai dengan tahap aktif.');
        }
        if ($stage->putaran_asal_id !== null && (int) $source->putaran_id !== (int) $stage->putaran_asal_id) {
            throw new LogicException('Putaran asal isian tidak sesuai dengan tahap.');
        }
        if (! $source->getConnection()->table('tb_surat_putaran')->where('id', $source->putaran_id)->where('record_id', $record->id)->exists()) {
            throw new LogicException('Putaran asal isian harus berasal dari surat yang sama.');
        }
        $unit = $this->sourceUnit($source, $butir);
        if ($unit === null || ! $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->contains(fn ($pic): bool => (int) $pic->unit_kerja_id === $unit)
            || $source->butir_pic_id !== null && ! $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->contains(fn ($pic): bool => (int) $pic->id === (int) $source->butir_pic_id && (int) $pic->unit_kerja_id === $unit)) {
            throw new LogicException('Unit pengisi harus dapat dibuktikan dari penugasan butir.');
        }
        $source->setAttribute('unit_kerja_id', $unit);
        $duplicates = $this->sources($butir, $stage)->filter(fn ($row): bool => $this->sourceUnit($row, $butir) === $unit
            && ! ($row->getTable() === $source->getTable() && (int) $row->id === (int) $source->id));
        if ($duplicates->isNotEmpty()) {
            throw new LogicException('Unit kerja ini sudah mengisi tahap dan putaran ini. Edit isian yang ada melalui Detail.');
        }
        if ($butir->record->isWorkflowClosed()) {
            $closedRound = $stage->putaran_asal_id ?? $butir->record->putaran_aktif_id ?? $butir->record->putaran->firstWhere('nomor_putaran', 0)?->id;
            if ($source->exists || $record->closed_at === null || (int) $record->closed_putaran_id !== (int) $closedRound) {
                throw new LogicException('Setelah Close hanya unit yang belum mengisi tahap terakhir dapat melengkapi.');
            }
        } elseif ($source->exists && $this->hasDecision($butir, $stage)) {
            throw new LogicException('Isian yang sudah direviu tidak boleh diubah.');
        }
        if (! $source->exists) {
            $source->setAttribute('submitted_after_close_at', $record->closed_at === null ? null : now());
            $source->setAttribute('submitted_after_compilation_at', $this->hasCompilation($butir, $stage) ? now() : null);
        } elseif ($this->hasCompilation($butir, $stage) && $source->isDirty(['tanggapan', 'tindak_lanjut', 'deliverables', 'dokumen'])) {
            $source->setAttribute('changed_after_compilation_at', now());
        }
    }

    public function register(Model $source): void
    {
        $module = $this->module($source);
        $db = $source->getConnection();
        $key = ['tahap_id' => $source->tahap_id, 'butir_key' => $source->getAttribute('id_butir_'.$module), 'unit_kerja_id' => $source->unit_kerja_id];
        $db->table('tb_isian_unit_tahap')->insertOrIgnore($key + ['tabel_sumber' => $source->getTable(), 'sumber_id' => $source->id, 'created_at' => now(), 'updated_at' => now()]);
        $slot = $db->table('tb_isian_unit_tahap')->where($key)->lockForUpdate()->first();
        if ($slot === null || $slot->tabel_sumber !== $source->getTable() || (int) $slot->sumber_id !== (int) $source->id) {
            throw new LogicException('Satu unit hanya boleh memiliki satu isian pada butir dan tahap yang sama.');
        }
    }

    private function log(Request $request, User $user, Model $source, string $action, ?array $old = null): void
    {
        LogActivity::create([
            'user_id' => $user->id, 'type_code' => $this->module($source), 'database_name' => 'sidewas_'.$this->module($source),
            'table_name' => $source->getTable(), 'record_key' => $source->getAttribute('id_butir_'.$this->module($source)),
            'action' => $action, 'description' => 'User menyimpan isian unit sesuai tahap dan putaran BRD 1.4.',
            'old_values' => $old, 'new_values' => $source->fresh()->toArray(),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
