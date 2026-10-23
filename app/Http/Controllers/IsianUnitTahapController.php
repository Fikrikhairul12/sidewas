<?php

namespace App\Http\Controllers;

use App\Enums\TahapProses;
use App\Models\Direktorat;
use App\Models\DjsnButir;
use App\Models\Komite;
use App\Models\SnpButir;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\IsianUnitTahapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IsianUnitTahapController extends Controller
{
    public function index(Request $request, string $module, TahapProses $type): View
    {
        $user = $request->user();
        $this->authorizePage($user, $module, $type);
        $class = $module === 'snp' ? SnpButir::class : DjsnButir::class;
        $query = $class::with([
            'record.tahapanProses', 'record.tahapAktif', 'record.putaran',
            'butirPics.unitKerja.direktorat', 'butirPics.komite',
            'tanggapanUnit.creator', 'tanggapanUnit.updater', 'tanggapanUnit.unitKerja',
            'tindakLanjuts.creator', 'tindakLanjuts.updater', 'tindakLanjuts.unitKerja', 'reviews', 'kompilasis',
        ])->with($module === 'snp' ? ['record.cluster', 'record.subCluster'] : ['cluster', 'subCluster'])->whereHas('record');
        if (! $user->isSuperAdmin() && ! $user->hasRoleType('admin_'.$module)) {
            $query->whereHas('butirPics', fn ($pics) => $pics->whereIn('jenis_pic', ['utama', 'pendukung'])->whereIn('unit_kerja_id', $user->unitKerjaIds()));
        }
        if ($type === TahapProses::TindakLanjut) {
            $query->whereHas('record.tahapanProses', fn ($stages) => $stages->where('tahap_proses', $type->value));
        }
        foreach (['tanggal_mulai' => '>=', 'tanggal_selesai' => '<='] as $field => $operator) {
            if ($request->filled($field)) {
                $query->whereDate('created_at', $operator, $request->input($field));
            }
        }
        foreach (['cluster_id', 'sub_cluster_id'] as $field) {
            if ($request->filled($field)) {
                if ($module === 'snp') {
                    $query->whereHas('record', fn ($record) => $record->where($field, $request->input($field)));
                } else {
                    $query->where($field, $request->input($field));
                }
            }
        }
        foreach (['unit_kerja_utama_id' => 'utama', 'unit_kerja_pendukung_id' => 'pendukung', 'komite_id' => 'komite'] as $field => $role) {
            if ($request->filled($field)) {
                $query->whereHas('butirPics', fn ($pics) => $pics->where('jenis_pic', $role)
                    ->where($role === 'komite' ? 'komite_id' : 'unit_kerja_id', $request->input($field)));
            }
        }
        if ($request->filled('direktorat_id')) {
            $ids = UnitKerja::where('direktorat_id', $request->input('direktorat_id'))->pluck('id');
            $query->whereHas('butirPics', fn ($pics) => $pics->where('jenis_pic', 'utama')->whereIn('unit_kerja_id', $ids));
        }
        if ($request->filled('keyword')) {
            $word = '%'.$request->input('keyword').'%';
            $query->where(fn ($search) => $search->where('id_butir_'.$module, 'like', $word)->orWhere('butir_'.$module, 'like', $word)
                ->orWhereHas('record', fn ($record) => $record->where('nomor_surat', 'like', $word)->orWhere('perihal_surat', 'like', $word))
                ->orWhereHas('tanggapanUnit', fn ($inputs) => $inputs->whereHas('tahapProses', fn ($stage) => $stage->where('tahap_proses', $type->value))
                    ->where(fn ($content) => $content->where('tanggapan', 'like', $word)->orWhere('deliverables', 'like', $word)))
                ->orWhereHas('tindakLanjuts', fn ($inputs) => $inputs->whereHas('tahapProses', fn ($stage) => $stage->where('tahap_proses', $type->value))
                    ->where(fn ($content) => $content->where('tindak_lanjut', 'like', $word)->orWhere('deliverables', 'like', $word))));
        }
        $service = app(IsianUnitTahapService::class);
        $this->filterStatus($query, $user, $type, (string) $request->input('status'));
        $entries = $query->latest('id')->paginate(2)->withQueryString()
            ->through(fn ($butir): array => ['butir' => $butir, 'state' => $service->presentation($butir, $user, $type)]);
        $clusterClass = '\\App\\Models\\'.ucfirst($module).'Cluster';

        return view('layouts.partials.isian-unit-tahap', [
            'entries' => $entries, 'module' => $module, 'type' => $type, 'typeLabel' => $type->label(),
            'actionLabel' => $type === TahapProses::TanggapanAwal ? 'Beri Tanggapan' : 'Beri Tindak Lanjut',
            'indexRoute' => $module.($type === TahapProses::TanggapanAwal ? '.tanggapan.index' : '.tindak-lanjut.index'),
            'clusters' => $clusterClass::with('subClusters')->orderBy('nama_cluster')->get(),
            'direktorats' => Direktorat::orderBy('nama_direktorat')->get(), 'unitKerjas' => UnitKerja::orderBy('nama_unit')->get(),
            'komites' => Komite::orderBy('nama_komite')->get(),
        ]);
    }

    public function store(Request $request, string $module, TahapProses $type, SnpButir|DjsnButir|null $butir = null): RedirectResponse
    {
        $this->authorizePage($request->user(), $module, $type);
        $data = $request->validate($this->rules() + [
            'butir_id' => [$butir === null ? 'required' : 'nullable', 'integer'],
            'butir_pic_id' => ['required', 'integer'],
            'tahap_id' => [$type === TahapProses::TindakLanjut ? 'required' : 'nullable', 'integer'],
        ]);
        $class = $module === 'snp' ? SnpButir::class : DjsnButir::class;
        $butir ??= $class::findOrFail($data['butir_id']);
        app(IsianUnitTahapService::class)->create($butir, $request->user(), $type, $data, $request);

        return redirect()->route($module.($type === TahapProses::TanggapanAwal ? '.tanggapan.index' : '.tindak-lanjut.index'))->with('success', $type->label().' berhasil disimpan.');
    }

    public function update(Request $request, Model $source): RedirectResponse
    {
        $module = $this->moduleFor($source);
        $type = $source->tahapProses?->tahap_proses;
        abort_unless($type, 422, 'Riwayat belum dipetakan ke tahap proses.');
        $this->authorizePage($request->user(), $module, $type);
        $data = $request->validate($this->rules());
        app(IsianUnitTahapService::class)->update($source, $request->user(), $data, $request);

        return redirect()->route($module.($type === TahapProses::TanggapanAwal ? '.tanggapan.index' : '.tindak-lanjut.index'))->with('success', 'Isian berhasil diperbarui melalui Detail.');
    }

    private function moduleFor(Model $source): string
    {
        return app(IsianUnitTahapService::class)->module($source);
    }

    private function filterStatus(Builder $query, User $user, TahapProses $type, string $status): void
    {
        if (! in_array($status, ['sudah', 'belum'], true)) {
            return;
        }
        $allUnits = $user->isSuperAdmin() || $user->hasRoleType('admin_'.$this->moduleFor($query->getModel()));
        $unitIds = $user->unitKerjaIds();
        $scope = function (Builder $sources) use ($type, $allUnits, $unitIds): void {
            $sources->whereHas('tahapProses', function (Builder $stage) use ($type): void {
                $stage->where('tahap_proses', $type->value);
                if ($type === TahapProses::TindakLanjut) {
                    $stage->whereHas('surat', fn ($record) => $record->whereColumn('tb_record.tahap_aktif_id', 'tb_surat_tahap.id'));
                }
            });
            if (! $allUnits) {
                $sources->where(fn ($unit) => $unit->whereIn('unit_kerja_id', $unitIds)
                    ->orWhere(fn ($unknown) => $unknown->whereNull('unit_kerja_id')->whereHas('butirPic', fn ($pic) => $pic->whereIn('unit_kerja_id', $unitIds))));
            }
        };
        if ($status === 'sudah') {
            $query->where(fn ($answered) => $answered->whereHas('tanggapanUnit', $scope)->orWhereHas('tindakLanjuts', $scope));
        } else {
            $query->whereDoesntHave('tanggapanUnit', $scope)->whereDoesntHave('tindakLanjuts', $scope);
        }
    }

    private function authorizePage(?User $user, string $module, TahapProses $type): void
    {
        abort_unless(in_array($module, ['snp', 'djsn'], true), 404);
        $method = 'canAccess'.ucfirst($module).($type === TahapProses::TanggapanAwal ? 'Tanggapan' : 'TindakLanjut');
        abort_unless($user && $user->{$method}(), 403);
    }

    private function rules(): array
    {
        return ['isi' => ['required', 'string'], 'deliverables' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120']];
    }
}
