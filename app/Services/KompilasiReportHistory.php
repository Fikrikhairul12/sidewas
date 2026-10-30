<?php

namespace App\Services;

use App\Enums\ReviuStatus;
use App\Models\DjsnButir;
use App\Models\DjsnKompilasi;
use App\Models\DjsnReview;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SnpReview;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class KompilasiReportHistory
{
    public function __construct(private KompilasiBahanReader $reader, private ReportDocumentService $documents) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function data(array $data, string $module): array
    {
        $relation = 'butir'.ucfirst($module);
        $records = $data['records'];
        $relations = [$relation.'.kompilasis.bahan', $relation.'.kompilasis.tahapProses',
            $relation.'.reviews.creator', $relation.'.reviews.updater', $relation.'.reviews.riwayatTanggal'];
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
                $rounds = $this->rounds($butir, $data);
                $butirs[] = ['record' => $record, 'butir' => $butir, 'id' => $butir->{'id_butir_'.$module}, 'rounds' => $rounds,
                    'unverified' => $butir->kompilasis->reject(fn ($compilation): bool => $rounds->contains('id', $compilation->id))
                        ->filter(fn ($compilation): bool => $compilation->salinan_konteks !== null || filled($compilation->hasil_kompilasi) || $compilation->status !== 'belum_dikompilasi')->count(),
                ];
            }
            if ($record->{$relation}->isEmpty()) {
                $butirs[] = ['record' => $record, 'butir' => null, 'id' => $record->{'id_'.$module}, 'rounds' => collect(), 'unverified' => 0];
            }
        }
        $fields = $data['selectedFields'] ?? ['surat', 'id_butir', 'isi_butir', 'pic_unit', 'tanggapan_tl', 'deliverable', 'dokumen', 'jatuh_tempo', 'komite', 'hasil_reviu', 'status'];
        $labels = app(WorkflowReportHistory::class)->labels($module);
        $data['workflowReport'] = [
            'module' => $module, 'butirs' => $butirs, 'fields' => $fields, 'compiled_only' => true,
            'labels' => array_replace($labels, ['tanggapan_tl' => 'BAHAN UNIT & HASIL KOMPILASI', 'tanggapan_unit' => 'TANGGAPAN AWAL UNIT KERJA',
                'tindak_lanjut_unit' => 'TINDAK LANJUT UNIT KERJA', 'kompilasi_tanggapan' => 'KOMPILASI TANGGAPAN AWAL', 'kompilasi_tindak_lanjut' => 'KOMPILASI TINDAK LANJUT']),
            'round_field' => collect($fields)->first(fn ($field): bool => in_array($field, WorkflowReportHistory::PROCESS_FIELDS, true)),
        ];

        return $data;
    }

    /** @param array<string, mixed> $selection
     * @return Collection<int, array<string, mixed>>
     */
    public function rounds(SnpButir|DjsnButir $butir, array $selection = []): Collection
    {
        $butir->loadMissing(['record', 'kompilasis.bahan', 'kompilasis.tahapProses', 'reviews.creator', 'reviews.updater', 'reviews.riwayatTanggal']);

        return $butir->kompilasis->map(function (SnpKompilasi|DjsnKompilasi $compilation) use ($butir, $selection): ?array {
            $compilation->setRelation('butir', $butir);
            $snapshot = $this->reader->read($compilation);
            if ($snapshot === null) {
                return null;
            }
            $followUp = $compilation->tahap_kompilasi === 'tindak_lanjut';
            $filter = array_map('intval', $selection[$followUp ? 'tindakLanjutUnitKerjaIds' : 'tanggapanUnitKerjaIds'] ?? []);
            $materials = $snapshot['materials']->filter(fn ($entry): bool => $filter === [] || in_array((int) $entry['unit_id'], $filter, true))->values();
            $missing = $snapshot['units']->where('filled', false)->filter(fn ($unit): bool => $filter === [] || in_array($unit['id'], $filter, true))->values();
            $reviews = $butir->reviews->filter(fn ($review): bool => $this->reader->matchesReview($compilation, $review))->sortBy('id')->values();
            $reviewEntries = $reviews->map(fn ($review): array => $this->reviewEntry($review, $compilation));
            $stage = $snapshot['stage'];

            return [
                'id' => $compilation->id, 'stage_id' => $stage->id, 'number' => $stage->nomor_putaran, 'label' => $snapshot['label'],
                'stage_type' => $compilation->tahap_kompilasi, 'captured_at' => $snapshot['compilation']['created_at'],
                'snapshot' => $snapshot['context'], 'units' => $snapshot['units'], 'assignments' => $snapshot['assignments'],
                'missing' => $missing, 'sources' => $materials,
                'responses' => $followUp ? collect() : $materials, 'followups' => $followUp ? $materials : collect(),
                'compilations' => collect([$snapshot['compilation']]), 'reviews' => $reviewEntries,
                'unverified_reviews' => $butir->reviews->where('kompilasi_id', $compilation->id)->count() - $reviews->count(),
                'all_entries' => $materials->concat([$snapshot['compilation']])->concat($reviewEntries)->values(),
                'date_history' => $reviews->flatMap->riwayatTanggal->sortBy('id')->values(),
            ];
        })->filter()->sortBy(fn ($row): array => [$row['number'], $row['captured_at']?->getTimestamp() ?? 0, $row['id']])->values();
    }

    /** The options use the same frozen unit identities as the report. */
    public function unitOptions(SnpButir $butir): Collection
    {
        return $this->rounds($butir)->flatMap(fn ($row) => $row['units'])->unique('id')
            ->map(fn ($unit): array => ['id' => $unit['id'], 'label' => trim(($unit['code'] ? $unit['code'].' - ' : '').$unit['name'])])->values();
    }

    /** @return array<string, mixed> */
    private function reviewEntry(SnpReview|DjsnReview $review, SnpKompilasi|DjsnKompilasi $compilation): array
    {
        $reviewed = $review->status !== ReviuStatus::BelumDitanggapi->value || filled($review->hasil_review);
        $actorId = $reviewed ? ($review->updated_by ?? $review->created_by) : $review->created_by;
        $actor = $reviewed && $review->updated_by !== null ? $review->updater : $review->creator;

        return [
            'id' => $review->id, 'title' => 'Reviu kompilasi', 'field' => 'hasil_review', 'unit' => null, 'unit_code' => null,
            'author' => $actor?->name ?? ($actorId ? 'Pengisi #'.$actorId.' (nama tidak tersedia)' : 'Pengisi tidak tercatat'),
            'created_at' => $reviewed ? $review->updated_at : null, 'updated_at' => null,
            'content' => $review->hasil_review, 'deliverables' => $review->deliverables,
            'documents' => $this->documents->documents($compilation, collect(['dokumen' => 'Dokumen reviu', 'dokumen_memo' => 'Memo reviu'])
                ->filter(fn ($label, $field): bool => filled($review->{$field}))
                ->mapWithKeys(fn ($label, $field): array => [$label => ['disk' => 'public', 'path' => $review->{$field}]])->all()),
            'decision' => ReviuStatus::labelFor($review->status), 'late_at' => null, 'proposed_date' => null,
        ];
    }
}
