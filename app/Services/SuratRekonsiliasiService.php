<?php

namespace App\Services;

use App\Enums\TahapProses;
use App\Models\KompilasiBahan;
use App\Models\LogActivity;
use App\Models\SuratRekonsiliasi;
use App\Models\SuratTahap;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Explicit evidence review, never a reconstruction from the current source timestamps. */
class SuratRekonsiliasiService
{
    public function __construct(private WorkflowReadinessService $readiness, private KompilasiBerkasService $files) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function run(array $input, User $actor, bool $apply = false): array
    {
        abort_unless($actor->status === 'active' && $actor->isSuperAdmin(), 403, 'Rekonsiliasi historis hanya dilakukan Super Admin yang memeriksa bukti.');
        validator($input, ['version' => ['required', 'in:1'], 'module' => ['required', 'in:snp,djsn'], 'record_id' => ['required', 'integer', 'min:1'],
            'expected_fingerprint' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'action' => ['required', 'in:konfirmasi_tahap,konfirmasi_tanggal,konfirmasi_unit,salinan_historis,arsip_historis'],
            'reason' => ['required', 'string', 'min:10', 'max:5000'], 'evidence' => ['required', 'array'], 'details' => ['present', 'array']])->validate();
        $module = $input['module'];
        $class = 'App\\Models\\'.ucfirst($module).'Record';
        $operation = hash('sha256', json_encode([$input, $actor->id], JSON_THROW_ON_ERROR));
        $copies = [];
        $proofPath = null;
        try {
            return (new $class)->getConnection()->transaction(function () use ($input, $module, $class, $actor, $apply, $operation, &$copies, &$proofPath): array {
                $record = $class::query()->lockForUpdate()->findOrFail($input['record_id']);
                $db = $record->getConnection();
                $completed = SuratRekonsiliasi::on($db->getName())->where('record_id', $record->id)->where('kode', 'r7_'.substr($operation, 0, 40))->first();
                if ($completed !== null) {
                    return ['applied' => true, 'already_applied' => true, 'operation_id' => $operation];
                }
                if ($this->readiness->fingerprint($module, $record->id) !== $input['expected_fingerprint']) {
                    $this->reject('Surat berubah setelah rencana dibuat. Periksa ulang bukti dan ambil fingerprint baru.');
                }
                $this->assertAsset($input['evidence']);
                $details = $input['details'];
                $stage = isset($details['stage_id']) ? SuratTahap::on($db->getName())->where('record_id', $record->id)->lockForUpdate()->findOrFail($details['stage_id']) : null;
                $changes = [];
                $compilation = null;
                $materials = collect();
                $context = null;
                if ($input['action'] === 'konfirmasi_tahap') {
                    $changes = $this->stagePlan($record, $stage, $details);
                } elseif ($input['action'] === 'konfirmasi_tanggal') {
                    $changes = $this->datePlan($record, $stage, $details);
                } elseif ($input['action'] === 'konfirmasi_unit') {
                    $changes = $this->unitPlan($record, $stage, $details);
                } elseif ($input['action'] === 'salinan_historis') {
                    [$compilation, $context, $materials] = $this->snapshotPlan($module, $record, $details);
                    $stage = $compilation->tahapProses;
                } else {
                    $closed = $record->isWorkflowClosed() || $record->status === 'tuntas';
                    if (! $closed && ($stage === null || $stage->archived_at === null || (int) $record->tahap_aktif_id === (int) $stage->id)) {
                        $this->reject('Arsip historis hanya untuk surat selesai atau tahap lama yang sudah terkunci. Tahap aktif tidak boleh diarsipkan untuk melewati pemeriksaan.');
                    }
                }
                $plan = ['applied' => false, 'operation_id' => $operation, 'module' => $module, 'record_id' => $record->id,
                    'action' => $input['action'], 'stage_id' => $stage?->id, 'changes' => $changes,
                    'compilation_id' => $compilation?->id, 'material_count' => $materials->count(), 'reason' => $input['reason']];
                if (! $apply) {
                    return $plan;
                }
                $destination = 'rekonsiliasi-r7/'.$operation.'.bin';
                if (Storage::disk('local')->exists($destination)) {
                    $this->reject('Berkas bukti tujuan sudah ada; periksa hasil operasi sebelumnya.');
                }
                $proofPath = $destination;
                if (! Storage::disk('local')->put($proofPath, Storage::disk($input['evidence']['disk'])->get($input['evidence']['path']))) {
                    $this->reject('Salinan bukti pemeriksaan gagal disimpan.');
                }
                $proof = ['disk' => 'local', 'path' => $proofPath, 'sha256' => $input['evidence']['sha256']];
                if ($input['action'] === 'konfirmasi_tahap') {
                    $db->table('tb_surat_tahap')->where('id', $stage->id)->update($changes['stage']);
                    $this->registerProvenInputs($record, $stage, true);
                    if ($changes['record'] !== []) {
                        $db->table('tb_record')->where('id', $record->id)->update($changes['record']);
                    }
                } elseif ($input['action'] === 'konfirmasi_tanggal') {
                    $db->table('tb_surat_tahap')->where('id', $stage->id)->update($changes + [
                        'tanggal_ditetapkan_by' => $actor->id, 'bukti_tanggal' => json_encode(($stage->bukti_tanggal ?? []) + ['rekonsiliasi_R7' => $proof, 'operation_id' => $operation], JSON_THROW_ON_ERROR),
                    ]);
                    $stage->riwayatTanggal()->create(['record_id' => $record->id, 'kunci' => 'rekonsiliasi:'.$operation, 'jenis' => 'rekonsiliasi_tanggal',
                        'tanggal_lama' => $stage->jatuh_tempo, 'tanggal_baru' => $changes['jatuh_tempo'], 'alasan' => $input['reason'],
                        'pengguna_id' => $actor->id, 'diputuskan_at' => now(), 'bukti' => ['dokumen' => $proof, 'pengguna_nama' => $actor->name, 'penetapan_historis_at' => $changes['tanggal_ditetapkan_at']]]);
                } elseif ($input['action'] === 'konfirmasi_unit') {
                    $db->table($details['table'])->where('id', $details['source_id'])->update(['unit_kerja_id' => $changes['unit_kerja_id']]);
                } elseif ($input['action'] === 'salinan_historis') {
                    $this->freezeBundle($context['butir'], $module, $record->id, $copies);
                    $this->freezeBundle($context['kompilasi'], $module, $record->id, $copies);
                    foreach (['dokumen', 'dokumen_memo'] as $field) {
                        $context['surat'][$field] = $this->freezeDocument($context['surat'][$field] ?? null, $copies);
                    }
                    foreach ($materials as $material) {
                        $copy = $material->salinan;
                        $this->freezeBundle($copy, $module, $record->id, $copies);
                        $material->salinan = $copy;
                        $material->bukti = ['jenis' => 'rekonsiliasi_historis_R7', 'dokumen' => $proof, 'operation_id' => $operation, 'diperiksa_by' => $actor->id];
                        $material->save();
                    }
                    $db->table('tb_kompilasi')->where('id', $compilation->id)->update(['salinan_konteks' => json_encode($context, JSON_THROW_ON_ERROR),
                        'status_bahan' => 'terverifikasi', 'bahan_dicatat_at' => Carbon::parse($context['dicatat_at'])->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                        'kunci_salinan' => hash('sha256', $stage->id.':'.$compilation->{'id_butir_'.$module})]);
                    foreach ($details['review_ids'] ?? [] as $reviewId) {
                        $db->table('tb_review')->where('id', $reviewId)->whereNull('kompilasi_id')->update(['kompilasi_id' => $compilation->id]);
                    }
                }
                $table = $input['action'] === 'konfirmasi_unit' ? $details['table'] : ($compilation !== null ? 'tb_kompilasi' : ($stage !== null ? 'tb_surat_tahap' : 'tb_record'));
                $sourceId = $input['action'] === 'konfirmasi_unit' ? $details['source_id'] : ($compilation?->id ?? $stage?->id ?? $record->id);
                SuratRekonsiliasi::on($db->getName())->create(['record_id' => $record->id, 'tahap_id' => $stage?->id, 'tabel_sumber' => $table, 'sumber_id' => $sourceId,
                    'kode' => 'r7_'.substr($operation, 0, 40), 'alasan' => $input['reason'], 'status' => 'selesai', 'diselesaikan_by' => $actor->id,
                    'diselesaikan_at' => now(), 'catatan_penyelesaian' => $input['reason'],
                    'bukti' => ['r7_action' => $input['action'], 'operation_id' => $operation, 'dokumen' => $proof, 'plan' => $plan, 'pengguna_nama' => $actor->name]]);
                $this->resolveProvenIssues($record, $stage, $compilation, $actor, $input['reason'], $input['action'], $details);
                LogActivity::create(['user_id' => $actor->id, 'type_code' => $module, 'database_name' => $db->getDatabaseName(), 'table_name' => $table,
                    'record_key' => $record->{'id_'.$module}, 'action' => 'rekonsiliasi_alur_R7', 'description' => $input['reason'],
                    'old_values' => ['fingerprint' => $input['expected_fingerprint']], 'new_values' => $plan + ['dokumen' => $proof]]);

                return array_replace($plan, ['applied' => true]);
            });
        } catch (Throwable $exception) {
            $this->files->discard($copies);
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function stagePlan(Model $record, ?SuratTahap $stage, array $details): array
    {
        if ($stage === null) {
            $this->reject('Pilih tahap surat yang akan diperiksa.');
        }
        validator($details, ['nomor_putaran' => ['required', 'integer', 'min:0'], 'aktif' => ['required', 'boolean']])->validate();
        $number = (int) $details['nomor_putaran'];
        if ($stage->nomor_putaran !== null && $stage->nomor_putaran !== $number
            || $stage->tahap_proses === TahapProses::TanggapanAwal && $number !== 0
            || $stage->tahap_proses === TahapProses::TindakLanjut && $number < 1
            || $stage->newQuery()->where('record_id', $record->id)->where('tahap_proses', $stage->tahap_proses->value)->where('nomor_putaran', $number)->where('id', '!=', $stage->id)->exists()) {
            $this->reject('Nomor putaran bertentangan dengan identitas lama atau tahap lainnya.');
        }
        $this->registerProvenInputs($record, $stage, false);
        $root = [];
        if ($details['aktif']) {
            $round = $stage->putaranAsal;
            if ($record->isWorkflowClosed() || $record->status === 'tuntas' || $stage->archived_at !== null || $round === null || $round->archived_at !== null
                || $record->tahap_aktif_id !== null && (int) $record->tahap_aktif_id !== (int) $stage->id
                || $record->putaran_aktif_id !== null && (int) $record->putaran_aktif_id !== (int) $round->id
                || $record->tahapanProses()->where('id', '!=', $stage->id)->where(fn ($query) => $query->whereNull('nomor_putaran')->orWhere('nomor_putaran', '>', $number))->exists()) {
                $this->reject('Tahap aktif bertentangan dengan riwayat surat. Close dan putaran yang sudah dilewati tidak dibuka kembali.');
            }
            $root['tahap_aktif_id'] = $stage->id;
            if (! $round->isLegacy()) {
                $root['putaran_aktif_id'] = $round->id;
            }
            if ($stage->tahap_proses === TahapProses::TindakLanjut) {
                $root['status'] = 'proses_tindak_lanjut_belum_sesuai';
            }
        }

        return ['stage' => ['nomor_putaran' => $number, 'status_pemetaan' => 'terpetakan'], 'record' => $root];
    }

    /** @param array<string, mixed> $details
     * @return array<string, string>
     */
    private function datePlan(Model $record, ?SuratTahap $stage, array $details): array
    {
        if ($stage === null || $stage->tanggal_ditetapkan_at !== null) {
            $this->reject('Tanggal yang sudah ditetapkan tidak boleh ditimpa melalui rekonsiliasi.');
        }
        validator($details, ['ditetapkan_at' => ['required', 'date'], 'mulai_at' => ['nullable', 'date'], 'jatuh_tempo' => ['nullable', 'date_format:Y-m-d']])->validate();
        $decided = Carbon::parse($details['ditetapkan_at'], 'Asia/Jakarta')->setTimezone('Asia/Jakarta');
        if ($decided->isFuture()) {
            $this->reject('Waktu penetapan historis tidak boleh berada di masa depan.');
        }
        $changes = ['tanggal_ditetapkan_at' => $decided->format('Y-m-d H:i:s')];
        if ($stage->tahap_proses === TahapProses::TanggapanAwal) {
            if (empty($details['mulai_at'])) {
                $this->reject('Tanggal pertama tersedia bagi unit harus dibuktikan.');
            }
            $start = Carbon::parse($details['mulai_at'], 'Asia/Jakarta')->setTimezone('Asia/Jakarta');
            if ($start->isFuture() || $stage->mulai_at !== null && ! $stage->mulai_at->equalTo($start)) {
                $this->reject('Tanggal awal bertentangan dengan jejak yang sudah ada.');
            }
            $changes['mulai_at'] = $start->format('Y-m-d H:i:s');
            $changes['jatuh_tempo'] = $start->copy()->addDays(14)->toDateString();
            if (isset($details['jatuh_tempo']) && $details['jatuh_tempo'] !== $changes['jatuh_tempo']) {
                $this->reject('Batas awal wajib 14 hari kalender sejak tersedia.');
            }
        } else {
            if (empty($details['jatuh_tempo']) || Carbon::parse($details['jatuh_tempo'], 'Asia/Jakarta')->endOfDay()->lessThan($decided)) {
                $this->reject('Jatuh tempo TL historis tidak boleh berakhir sebelum waktu keputusan yang dibuktikan.');
            }
            $changes['jatuh_tempo'] = $details['jatuh_tempo'];
        }

        return $changes;
    }

    /** @param array<string, mixed> $details
     * @return array{Model, array<string, mixed>, EloquentCollection}
     */
    private function snapshotPlan(string $module, Model $record, array $details): array
    {
        validator($details, ['compilation_id' => ['required', 'integer'], 'context' => ['required', 'array'], 'materials' => ['present', 'array'],
            'review_ids' => ['nullable', 'array'], 'review_ids.*' => ['integer']])->validate();
        $class = 'App\\Models\\'.ucfirst($module).'Kompilasi';
        $compilation = $class::query()->whereHas('butir', fn ($query) => $query->where('id_'.$module, $record->{'id_'.$module}))->lockForUpdate()->findOrFail($details['compilation_id']);
        if ($compilation->salinan_konteks !== null || $compilation->bahan()->exists()) {
            $this->reject('Salinan resmi yang sudah ada tidak boleh ditimpa.');
        }
        $context = $details['context'];
        $this->assertBundles($context);
        $captured = Carbon::parse($context['dicatat_at'] ?? '', 'Asia/Jakarta');
        if (empty($context['dicatat_at']) || $captured->isFuture()) {
            $this->reject('Waktu kompilasi harus berasal dari bukti historis.');
        }
        $stage = $compilation->tahapProses;
        if ($stage === null) {
            $this->reject('Hubungan tahap kompilasi harus diselesaikan terlebih dahulu.');
        }
        $materials = new EloquentCollection;
        foreach ($details['materials'] as $row) {
            validator($row, ['jenis_sumber' => ['required', 'in:tb_tanggapan,tb_tindak_lanjut'], 'sumber_id' => ['required', 'integer'],
                'unit_kerja_id' => ['required', 'integer', 'min:1'], 'salinan' => ['required', 'array']])->validate();
            $source = $record->getConnection()->table($row['jenis_sumber'])->where('id', $row['sumber_id'])->where('tahap_id', $stage->id)
                ->where('id_butir_'.$module, $compilation->{'id_butir_'.$module})->first();
            if ($source === null) {
                $this->reject('Sumber salinan harus terhubung ke butir dan tahap kompilasi yang sama.');
            }
            $this->assertBundles($row['salinan']);
            $material = new KompilasiBahan;
            $material->setConnection($record->getConnectionName());
            $material->forceFill(['kompilasi_id' => $compilation->id, 'tahap_id' => $stage->id, 'butir_key' => $compilation->{'id_butir_'.$module},
                'jenis_sumber' => $row['jenis_sumber'], 'sumber_id' => $row['sumber_id'], 'unit_kerja_id' => $row['unit_kerja_id'],
                'pengisi_id' => data_get($row, 'salinan.pengisi.id'),
                'sumber_created_at' => data_get($row, 'salinan.created_at') ? Carbon::parse($row['salinan']['created_at'])->setTimezone('Asia/Jakarta') : null,
                'sumber_updated_at' => data_get($row, 'salinan.updated_at') ? Carbon::parse($row['salinan']['updated_at'])->setTimezone('Asia/Jakarta') : null, 'salinan' => $row['salinan']]);
            $materials->push($material);
        }
        $candidate = clone $compilation;
        $candidate->forceFill(['salinan_konteks' => $context, 'status_bahan' => 'terverifikasi', 'bahan_dicatat_at' => $captured,
            'kunci_salinan' => hash('sha256', $stage->id.':'.$compilation->{'id_butir_'.$module})]);
        $candidate->setRelation('bahan', $materials);
        if (! app(KompilasiBahanReader::class)->verified($candidate)) {
            $this->reject('Salinan dan daftar bahan tidak memenuhi identitas serta kelengkapan resmi.');
        }
        foreach ($details['review_ids'] ?? [] as $id) {
            $review = $stage->reviu()->whereKey($id)->first();
            if ($review === null || $review->{'id_butir_'.$module} !== $compilation->{'id_butir_'.$module}
                || $review->kompilasi_id !== null && (int) $review->kompilasi_id !== (int) $compilation->id) {
                $this->reject('Hubungan reviu bertentangan dengan kompilasi.');
            }
            $review->kompilasi_id = $compilation->id;
            if (! app(KompilasiBahanReader::class)->matchesReview($candidate, $review)) {
                $this->reject('Komite atau tahap reviu tidak sesuai bukti kompilasi.');
            }
        }

        return [$compilation, $context, $materials];
    }

    private function registerProvenInputs(Model $record, SuratTahap $stage, bool $apply): void
    {
        $module = SuratTahap::moduleFor($record->getConnectionName());
        $db = $record->getConnection();
        $seen = [];
        foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $table) {
            foreach ($db->table($table)->where('tahap_id', $stage->id)->lockForUpdate()->get() as $source) {
                if (! $db->table('tb_butir_'.$module)->where('id_butir_'.$module, $source->{'id_butir_'.$module})->where('id_'.$module, $record->{'id_'.$module})->exists()) {
                    $this->reject('Tahap berisi sumber dari surat lain. Hubungan harus diperiksa terlebih dahulu.');
                }
                $pic = $source->butir_pic_id === null ? null : $db->table('tb_butir_pic')->where('id', $source->butir_pic_id)->where('id_butir_'.$module, $source->{'id_butir_'.$module})->first();
                $unit = $source->unit_kerja_id ?? $pic?->unit_kerja_id;
                $key = $source->{'id_butir_'.$module}.':'.$unit;
                if ($unit === null || isset($seen[$key]) || $source->butir_pic_id !== null && ($pic === null || (int) $pic->unit_kerja_id !== (int) $unit)) {
                    $this->reject('Unit belum terbukti atau ada isian ganda. Seluruh sumber dipertahankan untuk pemeriksaan.');
                }
                $seen[$key] = true;
                $slot = ['tahap_id' => $stage->id, 'butir_key' => $source->{'id_butir_'.$module}, 'unit_kerja_id' => $unit];
                $existing = $db->table('tb_isian_unit_tahap')->where($slot)->lockForUpdate()->first();
                if ($existing !== null && ($existing->tabel_sumber !== $table || (int) $existing->sumber_id !== (int) $source->id)) {
                    $this->reject('Registrasi unit bertentangan dengan sumber.');
                }
                if ($apply && $existing === null) {
                    $db->table('tb_isian_unit_tahap')->insert($slot + ['tabel_sumber' => $table, 'sumber_id' => $source->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    /** @param array<string, mixed> $details
     * @return array{unit_kerja_id: int, original_unit_kerja_id: null}
     */
    private function unitPlan(Model $record, ?SuratTahap $stage, array $details): array
    {
        validator($details, ['table' => ['required', 'in:tb_tanggapan,tb_tindak_lanjut'], 'source_id' => ['required', 'integer'], 'unit_kerja_id' => ['required', 'integer', 'min:1']])->validate();
        if ($stage === null || UnitKerja::find($details['unit_kerja_id']) === null) {
            $this->reject('Tahap dan unit kerja harus tersedia.');
        }
        $db = $record->getConnection();
        $module = SuratTahap::moduleFor($db->getName());
        $source = $db->table($details['table'])->where('tahap_id', $stage->id)->where('id', $details['source_id'])->lockForUpdate()->first();
        if ($source === null || $source->unit_kerja_id !== null || ! $db->table('tb_butir_'.$module)->where('id_butir_'.$module, $source->{'id_butir_'.$module})->where('id_'.$module, $record->{'id_'.$module})->exists()) {
            $this->reject('Hanya unit yang belum tercatat pada sumber surat ini yang dapat dilengkapi. Identitas yang sudah ada tidak ditimpa.');
        }
        if ($source->butir_pic_id !== null && ! $db->table('tb_butir_pic')->where('id', $source->butir_pic_id)->where('id_butir_'.$module, $source->{'id_butir_'.$module})->where('unit_kerja_id', $details['unit_kerja_id'])->exists()) {
            $this->reject('Unit bertentangan dengan PIC sumber.');
        }
        foreach (['tb_tanggapan', 'tb_tindak_lanjut'] as $table) {
            $others = $db->table($table)->where('tahap_id', $stage->id)->where('id_butir_'.$module, $source->{'id_butir_'.$module})
                ->when($table === $details['table'], fn ($query) => $query->where('id', '!=', $source->id))->lockForUpdate()->get();
            foreach ($others as $other) {
                $picUnit = $other->butir_pic_id === null ? null : $db->table('tb_butir_pic')->where('id', $other->butir_pic_id)
                    ->where('id_butir_'.$module, $source->{'id_butir_'.$module})->value('unit_kerja_id');
                if ((int) ($other->unit_kerja_id ?? $picUnit) === (int) $details['unit_kerja_id']) {
                    $this->reject('Ada isian lain untuk unit dan butir pada tahap yang sama. Periksa duplikasi terlebih dahulu.');
                }
            }
        }

        return ['unit_kerja_id' => (int) $details['unit_kerja_id'], 'original_unit_kerja_id' => null];
    }

    /** @param array<string, mixed> $asset */
    private function assertAsset(array $asset): void
    {
        validator($asset, ['disk' => ['required', 'in:local,public'], 'path' => ['required', 'string'], 'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/']])->validate();
        $path = $asset['path'];
        if (str_contains($path, '..') || str_contains($path, '\\') || str_contains($path, ':') || str_starts_with($path, '/') || str_contains($path, "\0")
            || ! Storage::disk($asset['disk'])->exists($path) || hash('sha256', Storage::disk($asset['disk'])->get($path)) !== $asset['sha256']) {
            $this->reject('Berkas bukti tidak tersedia atau hash tidak cocok.');
        }
    }

    /** @param array<string, mixed> $bundle */
    private function assertBundles(array $bundle): void
    {
        if (isset($bundle['disk'], $bundle['path'])) {
            $this->assertAsset($bundle);
        }
        if (array_key_exists('isi', $bundle)) {
            foreach (app(SnpButirContent::class)->images($bundle['isi']) as $image) {
                if (! collect($bundle['gambar'] ?? [])->contains(fn ($proof): bool => ($proof['path'] ?? null) === $image['path'])) {
                    $this->reject('Setiap gambar harus memiliki bukti versi berkas.');
                }
            }
        }
        foreach ($bundle as $child) {
            if (is_array($child)) {
                $this->assertBundles($child);
            }
        }
    }

    /** @param array<string, mixed> $bundle
     * @param  array<string, array<string, mixed>>  $copies
     */
    private function freezeBundle(array &$bundle, string $module, int $recordId, array &$copies): void
    {
        $bundle = array_merge($bundle, $this->files->content($bundle['isi'], $module, $recordId, $copies));
        $bundle['dokumen'] = $this->freezeDocument($bundle['dokumen'] ?? null, $copies);
    }

    private function freezeDocument(?array $document, array &$copies): ?array
    {
        if ($document === null) {
            return null;
        }
        if ($document['disk'] !== 'public') {
            $this->reject('Dokumen bahan harus berada di penyimpanan dokumen aplikasi.');
        }

        return $this->files->document($document['path'], $copies);
    }

    private function resolveProvenIssues(Model $record, ?SuratTahap $stage, ?Model $compilation, User $actor, string $reason, string $action, array $details): void
    {
        foreach ($record->rekonsiliasi()->where('status', 'terbuka')->lockForUpdate()->get() as $issue) {
            $resolved = $action === 'arsip_historis' && ($stage === null || (int) $issue->tahap_id === (int) $stage->id)
                || $action === 'konfirmasi_tahap' && (int) $issue->tahap_id === (int) $stage?->id && in_array($issue->kode, ['identitas_putaran_perlu_rekonsiliasi', 'nomor_tl_lama_perlu_rekonsiliasi', 'nomor_putaran_perlu_rekonsiliasi'], true)
                || $action === 'konfirmasi_tanggal' && (int) $issue->tahap_id === (int) $stage?->id && $issue->kode === 'tanggal_tahap_belum_terverifikasi'
                || $action === 'konfirmasi_unit' && $issue->kode === 'unit_pengisi_belum_terverifikasi' && $issue->tabel_sumber === $details['table'] && (int) $issue->sumber_id === (int) $details['source_id']
                || $action === 'salinan_historis' && $issue->tabel_sumber === 'tb_kompilasi' && (int) $issue->sumber_id === (int) $compilation?->id && $issue->kode === 'bahan_historis_belum_terbukti';
            if ($resolved) {
                $issue->fill(['status' => 'selesai', 'diselesaikan_by' => $actor->id, 'diselesaikan_at' => now(), 'catatan_penyelesaian' => $reason])->save();
            }
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['rekonsiliasi' => $message]);
    }
}
