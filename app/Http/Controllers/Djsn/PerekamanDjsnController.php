<?php

namespace App\Http\Controllers\Djsn;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\DjsnButir;
use App\Models\DjsnButirPic;
use App\Models\DjsnCluster;
use App\Models\DjsnRecord;
use App\Models\LogActivity;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PerekamanDjsnController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman perekaman DJSN.');
        }

        $recordsQuery = DjsnRecord::query()
            ->with([
                'creator',
                'butirDjsn.cluster',
                'butirDjsn.subCluster',
                'butirDjsn.butirPics.unitKerja.direktorat',
                'butirDjsn.butirPics.komite',
            ])
            ->withCount('butirDjsn');

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
            $recordsQuery->whereHas('butirDjsn', function ($butirQuery) use ($request) {
                $butirQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $recordsQuery->whereHas('butirDjsn', function ($butirQuery) use ($request) {
                $butirQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Kata Kunci
        |--------------------------------------------------------------------------
        | Cari dari id_djsn, nomor surat, perihal surat, id_butir_djsn, isi butir.
        */
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_djsn', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirDjsn', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_djsn', 'like', "%{$keyword}%")
                            ->orWhere('butir_djsn', 'like', "%{$keyword}%");
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

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($unitKerjaIds) {
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

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($unitKerjaUtamaId) {
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

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($unitKerjaPendukungId) {
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

            $recordsQuery->whereHas('butirDjsn.butirPics', function ($query) use ($komiteId) {
                $query->where('jenis_pic', 'komite')
                    ->where('komite_id', $komiteId);
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = DjsnCluster::with('subClusters')
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
            'total' => DjsnRecord::count(),
            'selesai' => DjsnRecord::where('status', 'selesai')->count(),
            'proses' => DjsnRecord::where('status', 'dalam_proses')->count(),
            'draft' => DjsnRecord::where('status', 'draft')->count(),
        ];

        return view('layouts.djsn.perekaman', compact(
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

        if (!$user || !$user->canCreateDjsnPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah perekaman DJSN.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'perihal_surat' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_djsn')->transaction(function () use ($request, $validated) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/record-djsn', 'public');
            }

            $record = DjsnRecord::create([
                'nomor_surat' => $validated['nomor_surat'],
                'tanggal_surat' => $validated['tanggal_surat'],
                'perihal_surat' => $validated['perihal_surat'],
                'dokumen' => $dokumenPath,
                'status' => 'draft',
            ]);

            LogActivity::create([
                'user_id' => Auth::id(),
                'type_code' => 'djsn',
                'database_name' => 'sidewas_djsn',
                'table_name' => 'tb_record',
                'record_key' => $record->id_djsn,
                'action' => 'create',
                'description' => 'User membuat perekaman surat DJSN Dewas.',
                'old_values' => null,
                'new_values' => $record->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('djsn.perekaman')
            ->with('success', 'Perekaman surat DJSN berhasil disimpan.');
    }

    public function storeButir(Request $request, DjsnRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateDjsnPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah perekaman DJSN.');
        }

        $validated = $request->validate([
            'butir_djsn' => ['required', 'string'],
            'cluster_id' => ['required', 'integer', 'exists:mysql_djsn.tb_cluster,id'],
            'sub_cluster_id' => ['required', 'integer', 'exists:mysql_djsn.tb_sub_cluster,id'],

            'unit_kerja_utama_id' => ['required', 'integer'],

            'unit_kerja_pendukung_id' => ['nullable', 'array'],
            'unit_kerja_pendukung_id.*' => ['nullable', 'integer'],

            'komite_id' => ['required', 'integer'],
        ]);

        DB::connection('mysql_djsn')->transaction(function () use ($request, $validated, $record) {
            $butir = DjsnButir::create([
                'id_djsn' => $record->id_djsn,
                'butir_djsn' => $validated['butir_djsn'],
                'cluster_id' => $validated['cluster_id'],
                'sub_cluster_id' => $validated['sub_cluster_id'],
            ]);

            DjsnButirPic::create([
                'id_butir_djsn' => $butir->id_butir_djsn,
                'unit_kerja_id' => $validated['unit_kerja_utama_id'],
                'komite_id' => null,
                'jenis_pic' => 'utama',
            ]);

            foreach (($validated['unit_kerja_pendukung_id'] ?? []) as $unitKerjaPendukungId) {
                if (!empty($unitKerjaPendukungId)) {
                    DjsnButirPic::create([
                        'id_butir_djsn' => $butir->id_butir_djsn,
                        'unit_kerja_id' => $unitKerjaPendukungId,
                        'komite_id' => null,
                        'jenis_pic' => 'pendukung',
                    ]);
                }
            }

            DjsnButirPic::create([
                'id_butir_djsn' => $butir->id_butir_djsn,
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
                'type_code' => 'djsn',
                'database_name' => 'sidewas_djsn',
                'table_name' => 'tb_butir_djsn',
                'record_key' => $butir->id_butir_djsn,
                'action' => 'create',
                'description' => 'User menambahkan butir DJSN pada surat ' . $record->id_djsn . '.',
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
            ->route('djsn.perekaman')
            ->with('success', 'Butir DJSN berhasil ditambahkan.');
    }

    public function requestDelete(Request $request, DjsnRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canRequestDeleteDjsnPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus perekaman DJSN.');
        }

        // Super admin langsung hapus
        if ($user->isSuperAdmin()) {
            DB::connection('mysql_djsn')->transaction(function () use ($request, $record, $user) {
                $oldData = $record->load([
                    'butirDjsn.butirPics',
                    'butirDjsn.cluster',
                    'butirDjsn.subCluster',
                ])->toArray();

                $recordKey = $record->id_djsn;

                if ($record->dokumen && Storage::disk('public')->exists($record->dokumen)) {
                    Storage::disk('public')->delete($record->dokumen);
                }

                $record->delete();

                LogActivity::create([
                    'user_id' => $user->id,
                    'type_code' => 'djsn',
                    'database_name' => 'sidewas_djsn',
                    'table_name' => 'tb_record',
                    'record_key' => $recordKey,
                    'action' => 'delete',
                    'description' => 'Super Admin menghapus perekaman DJSN secara langsung.',
                    'old_values' => $oldData,
                    'new_values' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            return redirect()
                ->route('djsn.perekaman')
                ->with('success', 'Perekaman DJSN berhasil dihapus.');
        }

        // Cegah request duplicate
        $existingRequest = DeleteRequest::where('type_code', 'djsn')
            ->where('table_name', 'tb_record')
            ->where('record_key', $record->id_djsn)
            ->whereIn('status', [
                'pending_admin_verification',
                'pending_super_admin_approval',
            ])
            ->first();

        if ($existingRequest) {
            return redirect()
                ->route('djsn.perekaman')
                ->with('error', 'Pengajuan hapus untuk data ini masih menunggu proses approval.');
        }

        $status = $user->isDjsnModerator()
            ? 'pending_admin_verification'
            : 'pending_super_admin_approval';

        DeleteRequest::create([
            'type_code' => 'djsn',
            'database_name' => 'sidewas_djsn',
            'table_name' => 'tb_record',
            'record_key' => $record->id_djsn,
            'record_label' => $record->id_djsn . ' - ' . $record->nomor_surat,
            'reason' => $request->input('reason'),
            'requested_by' => $user->id,
            'status' => $status,
            'requested_at' => now(),
        ]);

        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => 'djsn',
            'database_name' => 'sidewas_djsn',
            'table_name' => 'tb_record',
            'record_key' => $record->id_djsn,
            'action' => 'request_delete',
            'description' => 'User mengajukan penghapusan perekaman DJSN.',
            'old_values' => $record->toArray(),
            'new_values' => [
                'status_request' => $status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('djsn.perekaman')
            ->with('success', 'Pengajuan hapus berhasil dikirim.');
    }

    public function downloadDokumen(DjsnRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessDjsnPerekaman()) {
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
}
