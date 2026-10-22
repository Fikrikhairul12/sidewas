<?php

namespace App\Http\Controllers;

use App\Enums\ReviuStatus;
use App\Models\Direktorat;
use App\Models\DjsnCluster;
use App\Models\DjsnKompilasi;
use App\Models\DjsnReview;
use App\Models\Komite;
use App\Models\SnpCluster;
use App\Models\SnpKompilasi;
use App\Models\SnpReview;
use App\Models\UnitKerja;
use App\Services\ReviuPutaranService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviuPutaranController extends Controller
{
    public function __construct(private ReviuPutaranService $workflow) {}

    public function kompilasiIndex(Request $request, string $module = 'djsn'): View
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $class = $this->workflow->butirClass($module);
        $permission = 'canAccess'.ucfirst($module).'Kompilasi';
        abort_unless($request->user()?->{$permission}(), 403);
        $query = $class::with(['record.putaran', 'butirPics.unitKerja', 'kompilasis', 'reviews', 'tanggapanAktif.unitKerja', 'tanggapanAktif.creator'])->whereHas('record');
        $this->filters($query, $request, $module, false);
        if ($request->filled('status')) {
            $hasSubmitted = fn (Builder $q) => $q->padaPutaranAktif()->where('tahap_kompilasi', 'tanggapan')->where('status', 'dalam_proses_reviu_dewas');
            match ($request->status) {
                'belum_dikompilasi' => $query->whereDoesntHave('kompilasis', $hasSubmitted),
                'dalam_proses_reviu_dewas' => $query->whereHas('kompilasis', $hasSubmitted),
                default => $query->whereRaw('0 = 1'),
            };
        }
        $butirs = $query->latest('id')->paginate(2)->withQueryString();

        return view('layouts.partials.kompilasi-putaran', $this->viewData($module) + [
            'butirs' => $butirs, 'workflow' => $this->workflow,
            'statusOptions' => ['belum_dikompilasi' => 'Belum Dikompilasi', 'dalam_proses_reviu_dewas' => 'Dalam Proses Reviu Dewas'],
        ]);
    }

    public function kompilasiStore(Request $request, int $butir, string $module = 'djsn'): RedirectResponse
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $class = $this->workflow->butirClass($module);
        $permission = 'canCreate'.ucfirst($module).'Kompilasi';
        abort_unless($request->user()?->{$permission}(), 403);
        $validated = $request->validate([
            'putaran_id' => ['nullable', 'integer', 'min:1'],
            'tahap_kompilasi' => ['required', 'in:tanggapan'],
            'hasil_kompilasi' => ['required', 'string'], 'deliverables' => ['nullable', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'ubah_tgl' => ['nullable', 'date'],
        ]);
        $this->workflow->compile($class::findOrFail($butir), $request->user(), $validated, $request);

        return redirect()->route($module.'.kompilasi.index')->with('success', 'Kompilasi berhasil disimpan dan dikirim ke proses reviu Dewas.');
    }

    public function reviewIndex(Request $request, string $module): View
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $permission = 'canAccess'.ucfirst($module).'Review';
        abort_unless($request->user()?->{$permission}(), 403);
        $class = $this->workflow->reviewClass($module);
        $query = $class::with(['butir.record.putaran', 'kompilasiPutaran', 'putaran', 'komite', 'creator', 'updater', 'tanggapan', 'tindakLanjut'])->whereHas('butir.record');
        if (! $request->user()->isSuperAdmin()) {
            $query->whereIn('komite_id', $request->user()->komiteIds());
        }
        $this->filters($query, $request, $module, true);
        foreach (['status', 'tahap_review', 'komite_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        foreach (['tanggal_mulai' => '>=', 'tanggal_selesai' => '<='] as $field => $operator) {
            if ($request->filled($field)) {
                $query->whereDate('updated_at', $operator, $request->input($field));
            }
        }

        return view('layouts.partials.reviu-putaran', $this->viewData($module) + [
            'reviews' => $query->latest('id')->paginate(2)->withQueryString(),
            'workflow' => $this->workflow, 'statusOptions' => ReviuStatus::options(),
        ]);
    }

    public function reviewUpdate(Request $request, SnpReview|DjsnReview $review): RedirectResponse
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $permission = 'canReview'.ucfirst($this->workflow->module($review)).'ByKomite';
        abort_unless($request->user()?->{$permission}($review->komite_id), 403);
        abort_unless($this->workflow->canReview($request->user(), $review), 422, 'Reviu ini hanya dapat dibaca atau belum memiliki kompilasi yang valid.');
        $rules = [
            'hasil_review' => ['required', 'string'], 'deliverables' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(ReviuStatus::options()))],
            'status_pengajuan_tgl' => ['nullable', 'in:pending,disetujui,ditolak'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ];
        if ($review instanceof SnpReview) {
            $rules['dokumen_memo'] = $rules['dokumen'];
        }
        $nextRound = $this->workflow->review($review, $request->user(), $request->validate($rules), $request);

        return redirect()->route($this->workflow->module($review).'.reviu.index')->with('success', $nextRound
            ? 'Reviu berhasil disimpan. Putaran '.$nextRound->nomor_putaran.' dibuka untuk seluruh unit pada surat ini. Tanggapan baru diisi melalui halaman Tanggapan.'
            : 'Reviu berhasil disimpan.');
    }

    public function kompilasiDokumen(Request $request, int $kompilasi, string $module = 'djsn'): StreamedResponse
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $permission = 'canAccess'.ucfirst($module).'Kompilasi';
        abort_unless($request->user()?->{$permission}(), 403);
        $class = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;

        return $this->download($class::findOrFail($kompilasi)->dokumen);
    }

    public function reviewDokumen(Request $request, int $review, string $module): StreamedResponse
    {
        abort_unless(ReviuPutaranService::enabled(), 404);
        $class = $this->workflow->reviewClass($module);
        $model = $class::findOrFail($review);
        $permission = 'canReview'.ucfirst($module).'ByKomite';
        abort_unless($request->user()?->{$permission}($model->komite_id), 403);

        return $this->download($model->dokumen);
    }

    public function download(?string $path): StreamedResponse
    {
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Dokumen tidak ditemukan.');

        return Storage::disk('public')->download($path);
    }

    private function filters(Builder $query, Request $request, string $module, bool $reviews): void
    {
        $prefix = $reviews ? 'butir.' : '';
        foreach (['cluster_id', 'sub_cluster_id'] as $field) {
            if ($request->filled($field)) {
                $relation = $module === 'snp' ? $prefix.'record' : ($reviews ? 'butir' : null);
                $relation
                    ? $query->whereHas($relation, fn (Builder $q) => $q->where($field, $request->input($field)))
                    : $query->where($field, $request->input($field));
            }
        }
        foreach (['unit_kerja_utama_id' => 'utama', 'unit_kerja_pendukung_id' => 'pendukung', 'komite_id' => 'komite'] as $field => $type) {
            if ($request->filled($field) && ! ($reviews && $field === 'komite_id')) {
                $query->whereHas($prefix.'butirPics', fn (Builder $q) => $q->where('jenis_pic', $type)->where($type === 'komite' ? 'komite_id' : 'unit_kerja_id', $request->input($field)));
            }
        }
        if ($request->filled('direktorat_id')) {
            $units = UnitKerja::where('direktorat_id', $request->direktorat_id)->pluck('id');
            $query->whereHas($prefix.'butirPics', fn (Builder $q) => $q->whereIn('jenis_pic', ['utama', 'pendukung'])->whereIn('unit_kerja_id', $units));
        }
        if ($request->filled('keyword')) {
            $keyword = '%'.$request->keyword.'%';
            $query->where(function (Builder $q) use ($reviews, $module, $keyword): void {
                if ($reviews) {
                    $q->where('hasil_review', 'like', $keyword)->orWhere('deliverables', 'like', $keyword)
                        ->orWhereHas('kompilasiPutaran', fn (Builder $c) => $c->where('hasil_kompilasi', 'like', $keyword))
                        ->orWhereHas('butir', fn (Builder $b) => $this->butirKeyword($b, $module, $keyword));
                } else {
                    $this->butirKeyword($q, $module, $keyword);
                }
            });
        }
    }

    private function butirKeyword(Builder $query, string $module, string $keyword): void
    {
        $query->where('id_butir_'.$module, 'like', $keyword)->orWhere('butir_'.$module, 'like', $keyword)
            ->orWhereHas('record', fn (Builder $q) => $q->where('nomor_surat', 'like', $keyword)->orWhere('perihal_surat', 'like', $keyword)->orWhere('id_'.$module, 'like', $keyword))
            ->orWhereHas('tanggapanAktif', fn (Builder $q) => $q->where('tanggapan', 'like', $keyword)->orWhere('deliverables', 'like', $keyword));
    }

    /** @return array<string, mixed> */
    private function viewData(string $module): array
    {
        $class = $module === 'snp' ? SnpCluster::class : DjsnCluster::class;

        return [
            'module' => $module, 'moduleLabel' => $module === 'snp' ? 'SNP Dewas' : 'Rekomendasi DJSN',
            'clusters' => $class::with('subClusters')->orderBy('nama_cluster')->get(),
            'direktorats' => Direktorat::orderBy('nama_direktorat')->get(),
            'unitKerjas' => UnitKerja::orderBy('nama_unit')->get(),
            'komites' => Komite::orderBy('nama_komite')->get(),
        ];
    }
}
