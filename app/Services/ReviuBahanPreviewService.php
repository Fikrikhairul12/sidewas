<?php

namespace App\Services;

use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Models\SuratPutaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
    private function materials(SnpButir|DjsnButir $butir, ?SuratPutaran $round, Collection $responses): array
    {
        $module = $this->workflow->module($butir);
        $context = [
            'letter_number' => $butir->record?->nomor_surat ?? '-',
            'butir_id' => $butir->{'id_butir_'.$module},
            'round_id' => $round?->id,
            'round_label' => $round === null || $round->isLegacy() ? 'Putaran awal' : 'Putaran '.$round->nomor_putaran,
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
            'content' => $row->tanggapan, 'deliverables' => $row->deliverables,
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
