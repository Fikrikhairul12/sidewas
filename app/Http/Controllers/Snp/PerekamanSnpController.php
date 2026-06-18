<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpButirPic;
use App\Models\SnpCluster;
use App\Models\SnpRecord;
use App\Models\SnpTanggapan;
use App\Models\SnpTindakLanjut;
use App\Models\SnpReview;
use App\Models\SnpKompilasi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\DeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PerekamanSnpController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman perekaman SNP.');
        }

        $recordsQuery = SnpRecord::query()
            ->with([
                'cluster',
                'subCluster',
                'creator',
                'butirSnp.butirPics.unitKerja.direktorat',
                'butirSnp.butirPics.komite',
            ])
            ->withCount('butirSnp');

        /*
        |--------------------------------------------------------------------------
        | Filter Status
        |--------------------------------------------------------------------------
        */
        if ($request->filled('status')) {
            $recordsQuery->where('status', $request->status);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Tanggal Surat
        |--------------------------------------------------------------------------
        */
        if ($request->filled('tanggal_mulai')) {
            $recordsQuery->whereDate('tanggal_surat', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $recordsQuery->whereDate('tanggal_surat', '<=', $request->tanggal_selesai);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Cluster dan Sub Cluster
        |--------------------------------------------------------------------------
        */
        if ($request->filled('cluster_id')) {
            $recordsQuery->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('sub_cluster_id')) {
            $recordsQuery->where('sub_cluster_id', $request->sub_cluster_id);
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Kata Kunci
        |--------------------------------------------------------------------------
        | Cari dari id_snp, nomor surat, perihal surat, id_butir_snp, isi butir.
        */
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_snp', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirSnp', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_snp', 'like', "%{$keyword}%")
                            ->orWhere('butir_snp', 'like', "%{$keyword}%");
                    });
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Direktorat Penanggung Jawab / PIC Utama
        |--------------------------------------------------------------------------
        */
        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($unitKerjaIds) {
                $query->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Unit Kerja Utama
        |--------------------------------------------------------------------------
        */
        if ($request->filled('unit_kerja_utama_id')) {
            $unitKerjaUtamaId = $request->unit_kerja_utama_id;

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($unitKerjaUtamaId) {
                $query->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $unitKerjaUtamaId);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter PIC Pendukung
        |--------------------------------------------------------------------------
        */
        if ($request->filled('unit_kerja_pendukung_id')) {
            $unitKerjaPendukungId = $request->unit_kerja_pendukung_id;

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($unitKerjaPendukungId) {
                $query->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $unitKerjaPendukungId);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Komite
        |--------------------------------------------------------------------------
        */
        if ($request->filled('komite_id')) {
            $komiteId = $request->komite_id;

            $recordsQuery->whereHas('butirSnp.butirPics', function ($query) use ($komiteId) {
                $query->where('jenis_pic', 'komite')
                    ->where('komite_id', $komiteId);
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = SnpCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::with('unitKerja')
            ->orderBy('nama_direktorat')
            ->get();

        $unitKerjas = UnitKerja::with('direktorat')
            ->orderBy('nama_unit')
            ->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $statistik = [
            'total' => SnpRecord::count(),
            'selesai' => SnpRecord::where('status', 'selesai')->count(),
            'proses' => SnpRecord::where('status', 'dalam_proses')->count(),
            'draft' => SnpRecord::where('status', 'draft')->count(),
        ];

        return view('layouts.snp.perekaman', compact(
            'records',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statistik'
        ));
    }

    public function storeRecord(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah perekaman SNP.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'perihal_surat' => ['required', 'string'],
            'cluster_id' => ['required', 'integer'],
            'sub_cluster_id' => ['required', 'integer'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'dokumen_memo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated) {
            $dokumenPath = null;
            $dokumenMemoPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/record-snp', 'public');
            }

            if ($request->hasFile('dokumen_memo')) {
                $dokumenMemoPath = $request->file('dokumen_memo')->store('dokumen/memo-snp', 'public');
            }

            $record = SnpRecord::create([
                'cluster_id' => $validated['cluster_id'],
                'sub_cluster_id' => $validated['sub_cluster_id'],
                'nomor_surat' => $validated['nomor_surat'],
                'tanggal_surat' => $validated['tanggal_surat'],
                'perihal_surat' => $validated['perihal_surat'],
                'dokumen' => $dokumenPath,
                'dokumen_memo' => $dokumenMemoPath,
                'status' => 'draft',
            ]);

            LogActivity::create([
                'user_id' => Auth::id(),
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_record',
                'record_key' => $record->id_snp,
                'action' => 'create',
                'description' => 'User membuat perekaman surat SNP Dewas.',
                'old_values' => null,
                'new_values' => $record->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.perekaman')
            ->with('success', 'Perekaman surat SNP berhasil disimpan.');
    }

    public function storeButir(Request $request, SnpRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah perekaman SNP.');
        }

        $validated = $request->validate([
            'butir_snp' => ['required', 'string'],

            'unit_kerja_utama_id' => ['required', 'integer'],

            'unit_kerja_pendukung_id' => ['nullable', 'array'],
            'unit_kerja_pendukung_id.*' => ['nullable', 'integer'],

            'komite_id' => ['required', 'integer'],
        ]);

        DB::connection('mysql_snp')->transaction(function () use ($request, $validated, $record) {
            $butir = SnpButir::create([
                'id_snp' => $record->id_snp,
                'butir_snp' => $validated['butir_snp'],
            ]);

            SnpButirPic::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'unit_kerja_id' => $validated['unit_kerja_utama_id'],
                'komite_id' => null,
                'jenis_pic' => 'utama',
            ]);

            foreach (($validated['unit_kerja_pendukung_id'] ?? []) as $unitKerjaPendukungId) {
                if (!empty($unitKerjaPendukungId)) {
                    SnpButirPic::create([
                        'id_butir_snp' => $butir->id_butir_snp,
                        'unit_kerja_id' => $unitKerjaPendukungId,
                        'komite_id' => null,
                        'jenis_pic' => 'pendukung',
                    ]);
                }
            }

            SnpButirPic::create([
                'id_butir_snp' => $butir->id_butir_snp,
                'unit_kerja_id' => null,
                'komite_id' => $validated['komite_id'],
                'jenis_pic' => 'komite',
            ]);

            if ($record->status === 'draft') {
                $record->update([
                    'status' => 'terbit',
                ]);
            }

            LogActivity::create([
                'user_id' => Auth::id(),
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_butir_snp',
                'record_key' => $butir->id_butir_snp,
                'action' => 'create',
                'description' => 'User menambahkan butir SNP pada surat ' . $record->id_snp . '.',
                'old_values' => null,
                'new_values' => [
                    'record' => $record->fresh()->toArray(),
                    'butir' => $butir->toArray(),
                    'input' => $request->except('_token'),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('snp.perekaman')
            ->with('success', 'Butir SNP berhasil ditambahkan.');
    }

    public function requestDelete(Request $request, SnpRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canRequestDeleteSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus perekaman SNP.');
        }

        // Super admin langsung hapus
        if ($user->isSuperAdmin()) {
            DB::connection('mysql_snp')->transaction(function () use ($request, $record, $user) {
                $oldData = $record->load([
                    'butirSnp.butirPics',
                    'cluster',
                    'subCluster',
                ])->toArray();

                $recordKey = $record->id_snp;

                if ($record->dokumen && Storage::disk('public')->exists($record->dokumen)) {
                    Storage::disk('public')->delete($record->dokumen);
                }

                if ($record->dokumen_memo && Storage::disk('public')->exists($record->dokumen_memo)) {
                    Storage::disk('public')->delete($record->dokumen_memo);
                }

                $record->load([
                    'butirSnp.butirPics',
                ]);

                $butirIds = $record->butirSnp
                    ->pluck('id_butir_snp')
                    ->filter()
                    ->values()
                    ->toArray();

                if (!empty($butirIds)) {
                    $tanggapans = SnpTanggapan::whereIn('id_butir_snp', $butirIds)->get();

                    foreach ($tanggapans as $tanggapan) {
                        if ($tanggapan->dokumen && Storage::disk('public')->exists($tanggapan->dokumen)) {
                            Storage::disk('public')->delete($tanggapan->dokumen);
                        }
                    }

                    $tindakLanjuts = SnpTindakLanjut::whereIn('id_butir_snp', $butirIds)->get();

                    foreach ($tindakLanjuts as $tl) {
                        if ($tl->dokumen && Storage::disk('public')->exists($tl->dokumen)) {
                            Storage::disk('public')->delete($tl->dokumen);
                        }
                    }

                    $reviews = SnpReview::whereIn('id_butir_snp', $butirIds)->get();

                    foreach ($reviews as $review) {
                        if ($review->dokumen && Storage::disk('public')->exists($review->dokumen)) {
                            Storage::disk('public')->delete($review->dokumen);
                        }

                        if ($review->dokumen_memo && Storage::disk('public')->exists($review->dokumen_memo)) {
                            Storage::disk('public')->delete($review->dokumen_memo);
                        }
                    }

                    $kompilasis = SnpKompilasi::whereIn('id_butir_snp', $butirIds)->get();

                    foreach ($kompilasis as $kompilasi) {
                        if ($kompilasi->dokumen && Storage::disk('public')->exists($kompilasi->dokumen)) {
                            Storage::disk('public')->delete($kompilasi->dokumen);
                        }
                    }

                    SnpReview::whereIn('id_butir_snp', $butirIds)->delete();
                    SnpKompilasi::whereIn('id_butir_snp', $butirIds)->delete();
                    SnpTindakLanjut::whereIn('id_butir_snp', $butirIds)->delete();
                    SnpTanggapan::whereIn('id_butir_snp', $butirIds)->delete();

                    foreach ($record->butirSnp as $butir) {
                        $butir->butirPics()->delete();
                    }

                    $record->butirSnp()->delete();
                }

                $record->delete();

                LogActivity::create([
                    'user_id' => $user->id,
                    'type_code' => 'snp',
                    'database_name' => 'sidewas_snp',
                    'table_name' => 'tb_record',
                    'record_key' => $recordKey,
                    'action' => 'delete',
                    'description' => 'Super Admin menghapus perekaman SNP secara langsung.',
                    'old_values' => $oldData,
                    'new_values' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            return redirect()
                ->route('snp.perekaman')
                ->with('success', 'Perekaman SNP berhasil dihapus.');
        }

        // Cegah request duplicate
        $existingRequest = DeleteRequest::where('type_code', 'snp')
            ->where('table_name', 'tb_record')
            ->where('record_key', $record->id_snp)
            ->whereIn('status', [
                'pending_admin_verification',
                'pending_super_admin_approval',
            ])
            ->first();

        if ($existingRequest) {
            return redirect()
                ->route('snp.perekaman')
                ->with('error', 'Pengajuan hapus untuk data ini masih menunggu proses approval.');
        }

        $status = $user->isSnpModerator()
            ? 'pending_admin_verification'
            : 'pending_super_admin_approval';

        DeleteRequest::create([
            'type_code' => 'snp',
            'database_name' => 'sidewas_snp',
            'table_name' => 'tb_record',
            'record_key' => $record->id_snp,
            'record_label' => $record->id_snp . ' - ' . $record->nomor_surat,
            'reason' => $request->input('reason'),
            'requested_by' => $user->id,
            'status' => $status,
            'requested_at' => now(),
        ]);

        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => 'snp',
            'database_name' => 'sidewas_snp',
            'table_name' => 'tb_record',
            'record_key' => $record->id_snp,
            'action' => 'request_delete',
            'description' => 'User mengajukan penghapusan perekaman SNP.',
            'old_values' => $record->toArray(),
            'new_values' => [
                'status_request' => $status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('snp.perekaman')
            ->with('success', 'Pengajuan hapus berhasil dikirim.');
    }

    public function downloadDokumen(SnpRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen.');
        }

        if (!$record->dokumen) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $record->dokumen);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }

    public function downloadDokumenMemo(SnpRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessSnpPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen memo.');
        }

        if (!$record->dokumen_memo) {
            abort(404, 'Dokumen memo tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $record->dokumen_memo);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }
}
