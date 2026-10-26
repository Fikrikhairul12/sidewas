<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Models\DjsnButir;
use App\Models\SnpButir;
use App\Models\SuratPutaran;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WorkflowReportHistory
{
    public const PROCESS_FIELDS = [
        'tanggapan_tl', 'tanggapan_unit', 'tindak_lanjut_unit', 'kompilasi_tanggapan',
        'kompilasi_tindak_lanjut', 'deliverable', 'dokumen', 'jatuh_tempo', 'hasil_reviu', 'status',
    ];

    /** @return Collection<int, array{id: int, label: string}> */
    public function unitOptions(SnpButir $butir): Collection
    {
        if (IsianUnitTahapService::enabled()) {
            return app(KompilasiReportHistory::class)->unitOptions($butir);
        }
        $units = app(SuratPutaranPenugasan::class)->units($butir->butirPics);
        foreach ($butir->record->putaran as $round) {
            $units = $units->concat($round->penugasan_unit[$butir->id_butir_snp] ?? []);
        }
        foreach ($butir->tanggapanUnit->concat($butir->tindakLanjuts) as $entry) {
            $unit = method_exists($entry, 'unitKerja') ? $entry->unitKerja : null;
            $unit ??= $entry->butirPic?->unitKerja;
            $id = $this->unitId($entry);
            $units->push(['id' => $id ?? 0, 'code' => $unit?->kode_unit, 'name' => $unit?->nama_unit ?? ($id ? 'Unit #'.$id.' (nama tidak tersedia)' : 'Unit tidak tercatat (data lama)')]);
        }

        return $units->unique('id')->map(fn ($unit) => ['id' => (int) $unit['id'], 'label' => ($unit['code'] ? $unit['code'].' - ' : '').$unit['name']])->values();
    }

    /** @return array<string, string> */
    public function labels(string $module): array
    {
        return [
            'surat' => 'NOMOR, TANGGAL & PERIHAL SURAT',
            'id_butir' => 'ID BUTIR '.strtoupper($module), 'isi_butir' => 'ISI BUTIR '.strtoupper($module),
            'pic_unit' => 'PIC UNIT KERJA', 'tanggapan_tl' => 'TANGGAPAN UNIT & KOMPILASI',
            'tanggapan_unit' => 'TANGGAPAN UNIT KERJA', 'tindak_lanjut_unit' => 'TINDAK LANJUT LAMA UNIT KERJA',
            'kompilasi_tanggapan' => 'KOMPILASI TANGGAPAN', 'kompilasi_tindak_lanjut' => 'KOMPILASI TINDAK LANJUT LAMA',
            'deliverable' => 'DELIVERABLE', 'dokumen' => 'DOKUMEN PENDUKUNG',
            'jatuh_tempo' => 'JATUH TEMPO', 'komite' => 'KOMITE',
            'hasil_reviu' => 'HASIL & KEPUTUSAN REVIU', 'status' => 'STATUS SURAT TERKINI',
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function data(array $data, string $module): array
    {
        if (IsianUnitTahapService::enabled()) {
            return app(KompilasiReportHistory::class)->data($data, $module);
        }
        $records = $data['records'];
        $relation = 'butir'.ucfirst($module);
        $relations = [
            'putaran', $relation.'.butirPics.unitKerja', $relation.'.butirPics.komite',
            $relation.'.tanggapanUnit.unitKerja', $relation.'.tanggapanUnit.butirPic.unitKerja', $relation.'.tanggapanUnit.creator',
            $relation.'.kompilasis.creator', $relation.'.kompilasis.updater',
            $relation.'.reviews.creator', $relation.'.reviews.updater', $relation.'.reviews.komite',
            $relation.'.tindakLanjuts.creator',
        ];
        if ($module === 'snp') {
            $relations[] = $relation.'.tindakLanjuts.butirPic.unitKerja';
        }
        if ($records instanceof EloquentCollection) {
            $records->loadMissing($relations);
        } else {
            foreach ($records as $record) {
                $record->loadMissing($relations);
            }
        }
        $butirs = [];
        foreach ($records as $record) {
            foreach ($record->{$relation} as $butir) {
                $butir->setRelation('record', $record);
                $butirs[] = [
                    'record' => $record, 'butir' => $butir,
                    'id' => $butir->{'id_butir_'.$module},
                    'rounds' => $this->rounds($butir, $data),
                ];
            }
            if ($record->{$relation}->isEmpty()) {
                $butirs[] = ['record' => $record, 'butir' => null, 'id' => $record->{'id_'.$module}, 'rounds' => collect()];
            }
        }
        $fields = $data['selectedFields'] ?? ['surat', 'id_butir', 'isi_butir', 'pic_unit', 'tanggapan_tl', 'deliverable', 'dokumen', 'jatuh_tempo', 'komite', 'hasil_reviu', 'status'];
        $data['workflowReport'] = [
            'module' => $module, 'butirs' => $butirs, 'fields' => $fields, 'labels' => $this->labels($module),
            'round_field' => collect($fields)->first(fn ($field) => in_array($field, self::PROCESS_FIELDS, true)),
        ];

        return $data;
    }

    /** @param array<string, mixed> $selection
     * @return Collection<int, array<string, mixed>>
     */
    public function rounds(SnpButir|DjsnButir $butir, array $selection = []): Collection
    {
        if (IsianUnitTahapService::enabled()) {
            return app(KompilasiReportHistory::class)->rounds($butir, $selection);
        }
        $record = $butir->record;
        $headers = $record->putaran->sortBy('nomor_putaran')->values();
        $all = collect()->concat($butir->tanggapanUnit)->concat($butir->kompilasis)->concat($butir->reviews)->concat($butir->tindakLanjuts);
        if (! $headers->contains('nomor_putaran', 0) && ($headers->isEmpty() || $all->contains(fn ($entry) => $entry->putaran_id === null))) {
            $headers->prepend(null);
        }
        $rounds = $headers->map(function (?SuratPutaran $header) use ($butir, $record, $selection): array {
            $initial = $header === null || $header->nomor_putaran === 0;
            $inRound = fn ($entry) => $entry->putaran_id === null ? $initial : (int) $entry->putaran_id === (int) $header?->id;
            $responses = $butir->tanggapanUnit->filter($inRound)->sortBy(fn ($entry) => [$entry->created_at?->getTimestamp() ?? 0, $entry->id])->values();
            $compilations = $butir->kompilasis->filter($inRound)->sortBy('id')->values();
            $reviews = $butir->reviews->filter($inRound)->sortBy('id')->values();
            $followups = $butir->tindakLanjuts->filter($inRound)->sortBy('id')->values();
            $id = $butir->{'id_butir_'.($butir instanceof SnpButir ? 'snp' : 'djsn')};
            $snapshot = $header?->penugasan_unit;
            $current = $header?->archived_at === null && ! $record->isWorkflowClosed() && $record->status !== 'tuntas'
                && ($record->putaran_aktif_id === null ? $initial : (int) $header?->id === (int) $record->putaran_aktif_id);
            $units = $snapshot !== null ? collect($snapshot[$id] ?? []) : ($current ? app(SuratPutaranPenugasan::class)->units($butir->butirPics) : collect());
            $unknown = $snapshot === null && ! $current;
            $normal = $responses->whereNull('submitted_after_close_at');
            $late = $responses->whereNotNull('submitted_after_close_at');
            $responded = $responses->map(fn ($entry) => $this->unitId($entry))->filter()->all();
            $materialUnits = $normal->map(fn ($entry) => $this->unitId($entry))->filter()->all();
            $filter = array_map('intval', $selection['tanggapanUnitKerjaIds'] ?? []);
            $followupFilter = array_map('intval', $selection['tindakLanjutUnitKerjaIds'] ?? []);
            $include = fn ($entry) => $filter === [] || in_array($this->unitId($entry) ?? 0, $filter, true);
            $allEntries = $normal->filter($include)->map(fn ($entry) => $this->entry($entry, 'Tanggapan unit', 'tanggapan'));
            $lateEntries = $late->filter($include)->map(fn ($entry) => $this->entry($entry, 'Tanggapan setelah penutupan', 'tanggapan'));
            $compEntries = $compilations->map(fn ($entry) => $this->entry($entry, $entry->tahap_kompilasi === 'tindak_lanjut' ? 'Kompilasi Tindak Lanjut lama #'.($entry->putaran_tl ?? 1) : 'Kompilasi tanggapan', 'hasil_kompilasi'));
            $reviewEntries = $reviews->map(fn ($entry) => $this->entry($entry, $entry->tahap_review === 'tindak_lanjut' ? 'Reviu Tindak Lanjut lama #'.($entry->putaran_tl ?? 1) : 'Reviu tanggapan', 'hasil_review'));
            $followupEntries = $followups->filter(fn ($entry) => $followupFilter === [] || in_array($this->unitId($entry) ?? 0, $followupFilter, true))
                ->map(fn ($entry) => $this->entry($entry, 'Tindak Lanjut lama #'.($entry->putaran_tl ?? 1), 'tindak_lanjut'));
            $selectedUnits = $units->filter(fn ($unit) => $filter === [] || in_array((int) $unit['id'], $filter, true));

            return [
                'id' => $header?->id, 'label' => $initial ? 'Putaran awal (0)' : 'Putaran '.$header->nomor_putaran,
                'opened_at' => $header?->opened_at, 'archived_at' => $header?->archived_at,
                'units' => $units, 'unknown_assignments' => $unknown, 'current_assignments' => $snapshot === null && $current,
                'unidentified_responses' => $responses->contains(fn ($entry) => $this->unitId($entry) === null),
                'missing' => $selectedUnits->reject(fn ($unit) => in_array((int) $unit['id'], $responded, true))->values(),
                'missing_at_review' => $record->closed_putaran_id !== null && (int) $record->closed_putaran_id === (int) $header?->id
                    ? $selectedUnits->reject(fn ($unit) => in_array((int) $unit['id'], $materialUnits, true))->values() : collect(),
                'responses' => $allEntries->values(), 'late' => $lateEntries->values(),
                'compilations' => $compEntries, 'reviews' => $reviewEntries, 'followups' => $followupEntries->values(),
                'all_entries' => $allEntries->concat($compEntries)->concat($followupEntries)->concat($reviewEntries)->concat($lateEntries)->values(),
            ];
        });

        return $rounds->values();
    }

    private function unitId(Model $entry): ?int
    {
        $id = $entry->unit_kerja_id;
        if ($id === null && method_exists($entry, 'butirPic')) {
            $id = $entry->butirPic?->unit_kerja_id;
        }

        return $id === null ? null : (int) $id;
    }

    /** @return array<string, mixed> */
    private function entry(Model $entry, string $title, string $field): array
    {
        $unit = method_exists($entry, 'unitKerja') ? $entry->unitKerja : null;
        if ($unit === null && method_exists($entry, 'butirPic')) {
            $unit = $entry->butirPic?->unitKerja;
        }
        $unitId = $this->unitId($entry);
        $review = $field === 'hasil_review';
        $reviewed = $review && $entry->status !== 'belum_ditanggapi';
        $authorId = $reviewed ? ($entry->updated_by ?? $entry->created_by) : $entry->created_by;
        $author = $reviewed && $entry->updated_by !== null ? $entry->updater : $entry->creator;
        $documents = collect(['dokumen' => 'Dokumen', 'dokumen_memo' => 'Memo'])->filter(fn ($label, $attribute) => filled($entry->getAttribute($attribute)))
            ->map(fn ($label, $attribute) => ['label' => $label, 'url' => asset('storage/'.$entry->getAttribute($attribute))])->values();

        return [
            'id' => $entry->id, 'title' => $title, 'field' => $field,
            'unit_id' => $unitId,
            'unit' => $unit?->nama_unit ?? ($unitId ? 'Unit #'.$unitId.' (nama tidak tersedia)' : 'Unit tidak tercatat pada data lama'),
            'unit_code' => $unit?->kode_unit,
            'author' => $author?->name ?? ($authorId ? 'Pengisi #'.$authorId.' (nama tidak tersedia)' : 'Pengisi tidak tercatat'),
            'created_at' => $entry->created_at, 'updated_at' => $entry->updated_at,
            'content' => $entry->getAttribute($field), 'deliverables' => $entry->deliverables,
            'documents' => $documents, 'decision' => $review ? ReviuStatus::labelFor($entry->status) : null,
            'late_at' => $entry->getAttribute('submitted_after_close_at'),
            'proposed_date' => $entry->getAttribute('ubah_tgl'),
            'date_status' => $entry->getAttribute('status_pengajuan_tgl'),
            'legacy_round' => $entry->getAttribute('putaran_tl'),
            'stage' => $entry->getAttribute('tahap_kompilasi') ?? $entry->getAttribute('tahap_review'),
        ];
    }
}
