<?php

namespace App\Services;

use App\Enums\TahapProses;
use App\Models\DjsnKompilasi;
use App\Models\DjsnReview;
use App\Models\SnpKompilasi;
use App\Models\SnpReview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Shared, read-only source for the official review preview and PDF. */
class KompilasiBahanReader
{
    public function __construct(private IsianUnitTahapService $inputs, private ReportDocumentService $documents) {}

    public function verified(SnpKompilasi|DjsnKompilasi $compilation): bool
    {
        $compilation->loadMissing(['butir.record', 'tahapProses', 'bahan']);
        $stage = $compilation->tahapProses;
        $butir = $compilation->butir;
        $module = $this->inputs->module($compilation);
        $key = $compilation->{'id_butir_'.$module};
        $context = $compilation->salinan_konteks;
        if ($stage === null || $butir?->record === null || ! is_array($context)
            || $stage->status_pemetaan !== 'terpetakan' || $stage->nomor_putaran === null
            || (int) $stage->record_id !== (int) $butir->record->id
            || (int) $compilation->tahap_id !== (int) $stage->id
            || (int) $compilation->putaran_id !== (int) $stage->putaran_asal_id
            || $butir->{'id_butir_'.$module} !== $key
            || $stage->getConnectionName() !== $compilation->getConnectionName()
            || $compilation->status !== 'dalam_proses_reviu_dewas'
            || $compilation->status_bahan !== 'terverifikasi' || $compilation->bahan_dicatat_at === null
            || $compilation->kunci_salinan !== hash('sha256', $stage->id.':'.$key)) {
            return false;
        }
        $type = $stage->tahap_proses === TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut';
        if ($compilation->tahap_kompilasi !== $type
            || ($context['versi_salinan'] ?? null) !== 1
            || (int) data_get($context, 'surat.id') !== (int) $stage->record_id
            || (int) data_get($context, 'butir.id') !== (int) $butir->id || data_get($context, 'butir.key') !== $key
            || ! is_array($context['butir'] ?? null) || ! array_key_exists('isi', $context['butir'])
            || (int) data_get($context, 'tahap.id') !== (int) $stage->id
            || data_get($context, 'tahap.jenis') !== $stage->tahap_proses->value
            || (int) data_get($context, 'tahap.nomor_putaran') !== $stage->nomor_putaran
            || (int) data_get($context, 'tahap.putaran_asal_id') !== (int) $stage->putaran_asal_id
            || ! is_array($context['bahan'] ?? null) || ! is_array($context['kelengkapan_unit'] ?? null)
            || ! is_array($context['penugasan'] ?? null) || empty($context['dicatat_at'])
            || ! is_array($context['kompilasi'] ?? null)
            || ! array_key_exists('isi', $context['kompilasi'] ?? [])
            || ($context['jumlah_bahan'] ?? null) !== $compilation->bahan->count()
            || count($context['bahan']) !== $compilation->bahan->count()
            || $compilation->bahan->pluck('unit_kerja_id')->unique()->count() !== $compilation->bahan->count()) {
            return false;
        }
        foreach ($compilation->bahan as $material) {
            $copy = $material->salinan;
            $reference = collect($context['bahan'])->first(fn ($row): bool => ($row['tabel'] ?? null) === $material->jenis_sumber
                && (int) ($row['id'] ?? 0) === (int) $material->sumber_id);
            if (! is_array($copy) || $reference === null
                || ! in_array($material->jenis_sumber, ['tb_tanggapan', 'tb_tindak_lanjut'], true)
                || (int) $material->tahap_id !== (int) $stage->id || $material->butir_key !== $key
                || (int) ($copy['tahap_id'] ?? 0) !== (int) $stage->id || ($copy['butir_key'] ?? null) !== $key
                || (int) ($copy['record_id'] ?? 0) !== (int) $stage->record_id
                || ($copy['tahap_proses'] ?? null) !== $stage->tahap_proses->value
                || (int) ($copy['nomor_putaran'] ?? -1) !== $stage->nomor_putaran
                || (int) ($copy['putaran_asal_id'] ?? 0) !== (int) $stage->putaran_asal_id
                || data_get($copy, 'sumber.tabel') !== $material->jenis_sumber
                || (int) data_get($copy, 'sumber.id') !== (int) $material->sumber_id
                || (int) data_get($copy, 'unit.id') !== (int) $material->unit_kerja_id
                || ! array_key_exists('isi', $copy) || ! array_key_exists('deliverables', $copy)
                || (int) ($reference['unit_kerja_id'] ?? 0) !== (int) $material->unit_kerja_id
                || empty($copy['versi_sumber']) || ($reference['versi_sumber'] ?? null) !== $copy['versi_sumber']) {
                return false;
            }
        }
        $filled = $compilation->bahan->pluck('unit_kerja_id')->map(fn ($id): int => (int) $id)->all();
        $units = collect($context['kelengkapan_unit']);
        if ($units->pluck('id')->unique()->count() !== $units->count()) {
            return false;
        }
        foreach ($units as $unit) {
            if (! is_array($unit) || empty($unit['id']) || ! array_key_exists('terisi', $unit)
                || $unit['terisi'] !== in_array((int) $unit['id'], $filled, true)) {
                return false;
            }
        }
        if (array_diff($filled, $units->pluck('id')->map(fn ($id): int => (int) $id)->all()) !== []) {
            return false;
        }

        return true;
    }

    public function matchesReview(SnpKompilasi|DjsnKompilasi $compilation, SnpReview|DjsnReview $review): bool
    {
        $module = $this->inputs->module($compilation);
        $committee = collect($compilation->salinan_konteks['penugasan'] ?? [])->firstWhere('jenis_pic', 'komite');

        return $review->getConnectionName() === $compilation->getConnectionName()
            && (int) $review->kompilasi_id === (int) $compilation->id
            && $review->{'id_butir_'.$module} === $compilation->{'id_butir_'.$module}
            && (int) $review->tahap_id === (int) $compilation->tahap_id
            && (int) $review->putaran_id === (int) $compilation->putaran_id
            && $review->tahap_review === $compilation->tahap_kompilasi
            && (int) $review->komite_id === (int) ($committee['komite']['id'] ?? null);
    }

    /** @return array<string, mixed>|null */
    public function read(SnpKompilasi|DjsnKompilasi $compilation): ?array
    {
        if (! $this->verified($compilation)) {
            return null;
        }
        $context = $compilation->salinan_konteks;
        $stage = $compilation->tahapProses;
        $kind = $compilation->tahap_kompilasi === 'tindak_lanjut' ? 'Tindak Lanjut' : 'Tanggapan';
        $label = $stage->tahap_proses->label().($kind === 'Tindak Lanjut' ? ' Putaran '.$stage->nomor_putaran : '');
        $materials = $compilation->bahan->sortBy(fn ($material): string => ($material->salinan['created_at'] ?? '').sprintf('%020d', $material->id))
            ->map(function ($material) use ($kind, $compilation): array {
                $copy = $material->salinan;

                return $this->entry($material->id, $kind.' unit', $kind === 'Tanggapan' ? 'tanggapan' : 'tindak_lanjut', $copy['isi'], $copy['deliverables'],
                    $copy['pengisi'] ?? [], $copy['created_at'] ?? null, $copy['updated_at'] ?? null, $this->documents->documents($compilation, ['Dokumen' => $copy['dokumen'] ?? null])) + [
                        'source_id' => $material->sumber_id, 'source_table' => $material->jenis_sumber,
                        'unit_id' => $material->unit_kerja_id, 'unit' => $copy['unit']['nama'] ?? 'Unit #'.$material->unit_kerja_id,
                        'unit_code' => $copy['unit']['kode'] ?? null, 'late_at_submission' => $this->date(data_get($copy, 'keterlambatan.ditandai_at')),
                        'deadline_at_submission' => data_get($copy, 'keterlambatan.batas_saat_pengisian'),
                    ];
            })->values();
        $result = $this->entry($compilation->id, 'Kompilasi '.$label, 'hasil_kompilasi', $context['kompilasi']['isi'],
            $context['kompilasi']['deliverables'] ?? null, $context['penyusun'] ?? [], $context['dicatat_at'] ?? null, null,
            $this->documents->documents($compilation, ['Dokumen kompilasi' => $context['kompilasi']['dokumen'] ?? null])) + [
                'unit' => null, 'unit_code' => null, 'unit_id' => null,
            ];
        $result['stage'] = $compilation->tahap_kompilasi;
        $result['proposed_date'] = data_get($context, 'kompilasi.ubah_tgl');
        $result['date_status'] = $compilation->status_pengajuan_tgl;

        return ['context' => $context, 'stage' => $stage, 'label' => $label, 'kind_label' => $kind, 'materials' => $materials,
            'compilation' => $result, 'units' => collect($context['kelengkapan_unit'])->map(fn ($unit): array => [
                'id' => (int) $unit['id'], 'code' => $unit['kode'] ?? null, 'name' => $unit['nama'] ?? 'Unit #'.$unit['id'], 'filled' => (bool) $unit['terisi'],
            ])->values(), 'assignments' => collect($context['penugasan']),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function preview(array $data): array
    {
        $context = $data['context'];
        $identity = ['butir_id' => $context['butir']['key'], 'round_label' => $data['label']];
        $card = fn (array $entry): array => [
            'id' => $entry['id'], 'unit' => trim(($entry['unit_code'] ? $entry['unit_code'].' - ' : '').($entry['unit'] ?? '')),
            'author' => $entry['author'], 'date' => $entry['created_at']?->format('d/m/Y H:i') ?? '-',
            'content' => $entry['content'], 'deliverables' => $entry['deliverables'], 'documents' => $entry['documents']->all(),
            'after_close' => false,
        ] + $identity;

        return $identity + [
            'letter_number' => $context['surat']['nomor_surat'] ?? '-', 'butir_content' => $context['butir']['isi'],
            'round_id' => $context['tahap']['putaran_asal_id'], 'stage_id' => $context['tahap']['id'], 'kind_label' => $data['kind_label'],
            'responses' => $data['materials']->map($card)->all(), 'late_responses' => [], 'is_snapshot' => true, 'needs_reconciliation' => false,
            'missing_units' => $data['units']->where('filled', false)->pluck('name')->values()->all(),
            'compilation' => $card($data['compilation']),
        ];
    }

    /** @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    private function entry(int $id, string $title, string $field, ?string $content, ?string $deliverables, array $actor, ?string $createdAt, ?string $updatedAt, Collection $documents): array
    {
        return [
            'id' => $id, 'title' => $title, 'field' => $field, 'content' => $content, 'deliverables' => $deliverables,
            'author' => $actor['nama'] ?? (empty($actor['id']) ? 'Pengisi tidak tercatat' : 'Pengisi #'.$actor['id'].' (nama tidak tersedia)'),
            'created_at' => $this->date($createdAt), 'updated_at' => $this->date($updatedAt), 'documents' => $documents,
            'decision' => null, 'late_at' => null, 'proposed_date' => null, 'date_status' => null, 'stage' => null,
        ];
    }

    private function date(?string $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value)->setTimezone('Asia/Jakarta') : null;
    }
}
