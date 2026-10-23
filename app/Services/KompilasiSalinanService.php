<?php

namespace App\Services;

use App\Models\DjsnButir;
use App\Models\SnpButir;
use App\Models\SuratTahap;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LogicException;

class KompilasiSalinanService
{
    public function __construct(private IsianUnitTahapService $inputs, private KompilasiBerkasService $files) {}

    /**
     * @param  Collection<int, Model>  $sources
     * @param  array<string, array<string, mixed>>  $copies
     * @return array{context: array<string, mixed>, materials: list<array<string, mixed>>}
     */
    public function capture(SnpButir|DjsnButir $butir, SuratTahap $stage, Model $compilation, User $user, Collection $sources, Carbon $capturedAt, array &$copies): array
    {
        $module = $this->inputs->module($butir);
        $record = $butir->record;
        $butir->butirPics->loadMissing(['unitKerja.direktorat', 'komite']);
        (new EloquentCollection($sources->all()))->loadMissing(['unitKerja.direktorat', 'creator', 'updater']);
        $assignments = $butir->butirPics->map(fn ($pic): array => [
            'pic_id' => $pic->id, 'jenis_pic' => $pic->jenis_pic,
            'unit' => $pic->unit_kerja_id === null ? null : $this->unit($pic->unitKerja, (int) $pic->unit_kerja_id),
            'komite' => $pic->komite_id === null ? null : ['id' => (int) $pic->komite_id, 'nama' => $pic->komite?->nama_komite],
        ])->values();
        $materials = [];
        $seen = [];
        foreach ($sources as $source) {
            $unitId = $this->inputs->sourceUnit($source, $butir);
            if ($unitId === null || isset($seen[$unitId]) || (int) $source->putaran_id !== (int) $stage->putaran_asal_id
                || ! $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->contains(fn ($pic): bool => (int) $pic->unit_kerja_id === $unitId)
                || $source->submitted_after_close_at !== null || $source->submitted_after_compilation_at !== null) {
                throw new LogicException('Identitas bahan tidak sah atau ganda. Rekonsiliasi sumber sebelum kompilasi.');
            }
            $seen[$unitId] = true;
            $raw = $source->getRawOriginal();
            $version = hash('sha256', json_encode($raw, JSON_THROW_ON_ERROR));
            $content = $source->getTable() === 'tb_tanggapan' ? $source->tanggapan : $source->tindak_lanjut;
            $document = $this->files->document($source->dokumen, $copies);
            $unit = $source->unitKerja ?? $butir->butirPics->firstWhere('unit_kerja_id', $unitId)?->unitKerja;
            $snapshot = $this->files->content($content, $module, $record->id, $copies) + [
                'versi_salinan' => 1, 'versi_sumber' => $version,
                'sumber' => ['tabel' => $source->getTable(), 'id' => $source->id, 'data_asal' => $raw],
                'record_id' => $record->id, 'butir_key' => $butir->{'id_butir_'.$module},
                'tahap_id' => $stage->id, 'tahap_proses' => $stage->tahap_proses->value,
                'nomor_putaran' => $stage->nomor_putaran, 'putaran_asal_id' => $stage->putaran_asal_id,
                'deliverables' => $source->deliverables,
                'unit' => $this->unit($unit, $unitId),
                'pengisi' => $this->actor($source->creator, $source->created_by),
                'pengubah' => $this->actor($source->updater, $source->updated_by),
                'created_at' => $source->created_at?->toISOString(), 'updated_at' => $source->updated_at?->toISOString(),
                'dokumen' => $document, 'penugasan' => $assignments->where('unit.id', $unitId)->values()->all(),
                'dicatat_at' => $capturedAt->toISOString(),
            ];
            $materials[] = [
                'tahap_id' => $stage->id, 'jenis_sumber' => $source->getTable(), 'sumber_id' => $source->id,
                'butir_key' => $butir->{'id_butir_'.$module}, 'unit_kerja_id' => $unitId, 'pengisi_id' => $source->created_by,
                'sumber_created_at' => $source->created_at, 'sumber_updated_at' => $source->updated_at,
                'salinan' => $snapshot,
                'bukti' => ['jenis' => 'rekam_saat_kompilasi', 'versi_sumber' => $version, 'dicatat_at' => $capturedAt->toISOString(), 'dicatat_by' => $user->id],
            ];
        }
        $units = $assignments->whereIn('jenis_pic', ['utama', 'pendukung'])->whereNotNull('unit.id')->unique('unit.id');
        $completeness = $units->map(fn ($assignment): array => $assignment['unit'] + [
            'terisi' => isset($seen[$assignment['unit']['id']]),
            'sumber' => collect($materials)->where('unit_kerja_id', $assignment['unit']['id'])
                ->map(fn ($material): array => ['tabel' => $material['jenis_sumber'], 'id' => $material['sumber_id']])->values()->all(),
        ])->values()->all();

        return ['context' => [
            'versi_salinan' => 1, 'dicatat_at' => $capturedAt->toISOString(), 'penyusun' => $this->actor($user, $user->id),
            'surat' => ['id' => $record->id, 'id_surat' => $record->{'id_'.$module}, 'nomor_surat' => $record->nomor_surat,
                'tanggal_surat' => $record->getRawOriginal('tanggal_surat'), 'perihal' => $record->perihal_surat,
                'dokumen' => $this->files->document($record->dokumen, $copies), 'dokumen_memo' => $this->files->document($record->getAttribute('dokumen_memo'), $copies)],
            'butir' => ['id' => $butir->id, 'key' => $butir->{'id_butir_'.$module}]
                + $this->files->content($butir->{'butir_'.$module}, $module, $record->id, $copies),
            'tahap' => ['id' => $stage->id, 'jenis' => $stage->tahap_proses->value, 'nomor_putaran' => $stage->nomor_putaran,
                'putaran_asal_id' => $stage->putaran_asal_id, 'penugasan_saat_dibuka' => $stage->penugasan_unit,
                'mulai_at' => $stage->mulai_at?->toISOString(), 'jatuh_tempo' => $stage->jatuh_tempo?->toDateString()],
            'kompilasi' => $this->files->content($compilation->hasil_kompilasi, $module, $record->id, $copies) + [
                'deliverables' => $compilation->deliverables, 'dokumen' => $this->files->document($compilation->dokumen, $copies),
                'ubah_tgl' => $compilation->ubah_tgl?->toDateString(), 'status_pengajuan_tgl' => $compilation->status_pengajuan_tgl],
            'penugasan' => $assignments->all(), 'kelengkapan_unit' => $completeness, 'jumlah_bahan' => count($materials),
            'bahan' => collect($materials)->map(fn ($material): array => ['tabel' => $material['jenis_sumber'], 'id' => $material['sumber_id'],
                'unit_kerja_id' => $material['unit_kerja_id'], 'versi_sumber' => $material['salinan']['versi_sumber']])->all(),
        ], 'materials' => $materials];
    }

    /** @return array<string, mixed> */
    private function unit(?Model $unit, int $id): array
    {
        return ['id' => $id, 'nama' => $unit?->nama_unit, 'kode' => $unit?->kode_unit,
            'direktorat' => ['id' => $unit?->direktorat_id, 'nama' => $unit?->direktorat?->nama_direktorat]];
    }

    /** @return array{id: ?int, nama: ?string, email: ?string} */
    private function actor(?User $user, ?int $id): array
    {
        return ['id' => $id, 'nama' => $user?->name, 'email' => $user?->email];
    }
}
