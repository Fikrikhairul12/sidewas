<?php

namespace App\Services;

use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Models\SuratPutaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReviuBahanPreviewService
{
    public function __construct(private ReviuPutaranService $workflow) {}

    /** @return array<string, mixed> */
    public function forCompilation(SnpButir|DjsnButir $butir, User $user): array
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $permission = 'canAccess'.ucfirst($this->workflow->module($butir)).'Kompilasi';
        abort_unless($user->{$permission}(), 403);
        if (IsianUnitTahapService::enabled()) {
            $butir->loadMissing(['record.tahapanProses', 'record.tahapAktif', 'record.putaran', 'butirPics',
                'tanggapanUnit.unitKerja', 'tanggapanUnit.creator', 'tindakLanjuts.unitKerja', 'tindakLanjuts.creator']);
            $workflow = app(KompilasiTahapService::class);
            $round = $butir->record?->putaran->firstWhere('id', $workflow->stage($butir)?->putaran_asal_id);

            return $this->materials($butir, $round, $workflow->sources($butir), $workflow->label($butir));
        }
        $butir->loadMissing(['record.putaran', 'tanggapanAktif.unitKerja', 'tanggapanAktif.creator']);
        $round = $butir->record?->putaran->firstWhere('id', $this->workflow->roundId($butir));

        return $this->materials($butir, $round, $butir->tanggapanAktif);
    }

    /** @return array<string, mixed> */
    public function forReview(SnpReview|DjsnReview $review, User $user): array
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $permission = 'canAccess'.ucfirst($this->workflow->module($review)).'Review';
        abort_unless($user->{$permission}()
            && ($user->isSuperAdmin() || in_array($review->komite_id, $user->komiteIds())), 403);
        if (IsianUnitTahapService::enabled()) {
            return $this->snapshotMaterials($review);
        }
        $review->loadMissing([
            'butir.record.putaran', 'butir.tanggapanUnit.unitKerja', 'butir.tanggapanUnit.creator',
            'kompilasiPutaran.creator', 'kompilasiPutaran.updater',
        ]);
        $butir = $review->butir;
        if ($butir === null || $butir->record === null) {
            return ['responses' => [], 'late_responses' => [], 'compilation' => null];
        }
        $round = $review->putaran_id === null
            ? $butir->record->putaran->firstWhere('nomor_putaran', 0)
            : $butir->record->putaran->firstWhere('id', $review->putaran_id);
        $responses = $review->putaran_id !== null && $round === null ? collect() : $butir->tanggapanUnit;
        $materials = $this->materials($butir, $round, $responses);
        $compilation = $review->kompilasiPutaran;
        $module = $this->workflow->module($review);
        if (($review->putaran_id === null || $round !== null)
            && $compilation !== null && $compilation->getConnectionName() === $review->getConnectionName()
            && $compilation->{'id_butir_'.$module} === $review->{'id_butir_'.$module}
            && (int) $compilation->putaran_id === (int) $review->putaran_id
            && $compilation->tahap_kompilasi === 'tanggapan') {
            $materials['compilation'] = [
                'id' => $compilation->id,
                'content' => $compilation->hasil_kompilasi,
                'deliverables' => $compilation->deliverables,
                'author' => $compilation->creator?->name ?? 'Penyusun tidak tercatat (data lama)',
                'date' => $compilation->created_at?->format('d/m/Y H:i') ?? '-',
                'butir_id' => $materials['butir_id'], 'round_label' => $materials['round_label'],
            ];
        }

        return $materials;
    }

    /** @return array<string, mixed> */
    private function snapshotMaterials(SnpReview|DjsnReview $review): array
    {
        $workflow = app(ReviuTahapService::class);
        if (! $workflow->hasVerifiedCompilation($review)) {
            return ['responses' => [], 'late_responses' => [], 'compilation' => null, 'needs_reconciliation' => true];
        }
        $compilation = $review->kompilasiPutaran;
        $snapshot = $compilation->salinan_konteks;
        $context = [
            'letter_number' => $snapshot['surat']['nomor_surat'], 'butir_id' => $snapshot['butir']['key'],
            'butir_content' => $snapshot['butir']['isi'], 'round_id' => $review->putaran_id,
            'stage_id' => $review->tahap_id, 'round_label' => $workflow->label($review->tahapProses),
            'kind_label' => $review->tahap_review === 'tindak_lanjut' ? 'Tindak Lanjut' : 'Tanggapan',
        ];
        $responses = $compilation->bahan->sortBy(fn ($material): string => ($material->salinan['created_at'] ?? '').sprintf('%020d', $material->id))
            ->map(function ($material) use ($context): array {
                $copy = $material->salinan;

                return [
                    'id' => $material->id, 'unit' => trim(($copy['unit']['kode'] ? $copy['unit']['kode'].' - ' : '').($copy['unit']['nama'] ?? 'Unit #'.$material->unit_kerja_id)),
                    'author' => $copy['pengisi']['nama'] ?? 'Pengisi tidak tercatat (data lama)',
                    'date' => empty($copy['created_at']) ? '-' : Carbon::parse($copy['created_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i'),
                    'content' => $copy['isi'], 'deliverables' => $copy['deliverables'], 'after_close' => false,
                    'butir_id' => $context['butir_id'], 'round_label' => $context['round_label'],
                ];
            })->values()->all();

        return $context + [
            'responses' => $responses, 'late_responses' => [], 'is_snapshot' => true, 'needs_reconciliation' => false,
            'missing_units' => collect($snapshot['kelengkapan_unit'])->where('terisi', false)->pluck('nama')->all(),
            'compilation' => [
                'id' => $compilation->id, 'content' => $snapshot['kompilasi']['isi'], 'deliverables' => $snapshot['kompilasi']['deliverables'],
                'author' => $snapshot['penyusun']['nama'] ?? 'Penyusun tidak tercatat (data lama)',
                'date' => Carbon::parse($snapshot['dicatat_at'])->setTimezone(config('app.timezone'))->format('d/m/Y H:i'),
                'butir_id' => $context['butir_id'], 'round_label' => $context['round_label'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function materials(SnpButir|DjsnButir $butir, ?SuratPutaran $round, Collection $responses, ?string $stageLabel = null): array
    {
        $module = $this->workflow->module($butir);
        $context = [
            'letter_number' => $butir->record?->nomor_surat ?? '-',
            'butir_id' => $butir->{'id_butir_'.$module},
            'round_id' => $round?->id,
            'round_label' => $stageLabel ?? ($round === null || $round->isLegacy() ? 'Putaran awal' : 'Putaran '.$round->nomor_putaran),
        ];
        $matching = $responses->filter(fn (Model $row): bool => $row->getConnectionName() === $butir->getConnectionName()
            && $row->{'id_butir_'.$module} === $butir->{'id_butir_'.$module}
            && ($row->putaran_id === null ? $round === null || $round->isLegacy() : (int) $row->putaran_id === (int) $round?->id))
            ->sortBy(fn (Model $row): string => ($row->created_at?->format('Y-m-d H:i:s') ?? '').sprintf('%020d', $row->id));
        $cards = $matching->map(fn (Model $row): array => [
            'id' => $row->id, 'unit' => $row->unitKerja
                ? trim(($row->unitKerja->kode_unit ? $row->unitKerja->kode_unit.' - ' : '').$row->unitKerja->nama_unit)
                : 'Unit tidak tercatat (data lama)',
            'author' => $row->creator?->name ?? 'Pengisi tidak tercatat (data lama)',
            'date' => $row->created_at?->format('d/m/Y H:i') ?? '-',
            'content' => $row->getTable() === 'tb_tindak_lanjut' ? $row->tindak_lanjut : $row->tanggapan, 'deliverables' => $row->deliverables,
            'after_close' => $row->submitted_after_close_at !== null,
            'after_close_at' => $row->submitted_after_close_at?->format('d/m/Y H:i'),
            'butir_id' => $context['butir_id'], 'round_label' => $context['round_label'],
        ]);

        return $context + [
            'responses' => $cards->where('after_close', false)->values()->all(),
            'late_responses' => $cards->where('after_close', true)->values()->all(),
            'compilation' => null,
        ];
    }
}
