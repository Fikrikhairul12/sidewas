<?php

namespace App\Http\Controllers\Ragab;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RagabButir;
use App\Models\RagabButirPic;
use App\Models\RagabCluster;
use App\Models\RagabRecord;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\DeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PerekamanRagabController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman perekaman RAGAB.');
        }

        $recordsQuery = RagabRecord::query()
            ->with([
                'cluster',
                'subCluster',
                'creator',
                'butirRagab.butirPics.unitKerja.direktorat',
                'butirRagab.butirPics.komite',
            ])
            ->withCount('butirRagab');

        if ($request->filled('status')) {
            $recordsQuery->where('status', $request->status);
        }

        if ($request->filled('tanggal_mulai')) {
            $recordsQuery->whereDate('tanggal_surat', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $recordsQuery->whereDate('tanggal_surat', '<=', $request->tanggal_selesai);
        }

        if ($request->filled('cluster_id')) {
            $recordsQuery->where('cluster_id', $request->cluster_id);
        }

        if ($request->filled('sub_cluster_id')) {
            $recordsQuery->where('sub_cluster_id', $request->sub_cluster_id);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_ragab', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirRagab', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_ragab', 'like', "%{$keyword}%")
                            ->orWhere('butir_ragab', 'like', "%{$keyword}%");
                    });
            });
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::where('direktorat_id', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $recordsQuery->whereHas('butirRagab.butirPics', function ($query) use ($unitKerjaIds) {
                $query->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $recordsQuery->whereHas('butirRagab.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $request->unit_kerja_utama_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $recordsQuery->whereHas('butirRagab.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $recordsQuery->whereHas('butirRagab.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = RagabCluster::with('subClusters')
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
            'total' => RagabRecord::count(),
            'selesai' => RagabRecord::where('status', 'selesai')->count(),
            'proses' => RagabRecord::where('status', 'dalam_proses')->count(),
            'draft' => RagabRecord::where('status', 'draft')->count(),
        ];

        return view('layouts.ragab.perekaman', compact(
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

        if (!$user || !$user->canCreateRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah perekaman RAGAB.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'perihal_surat' => ['required', 'string'],
            'cluster_id' => ['required', 'integer'],
            'sub_cluster_id' => ['required', 'integer'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_ragab')->transaction(function () use ($request, $validated) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/record-ragab', 'public');
            }

            $record = RagabRecord::create([
                'cluster_id' => $validated['cluster_id'],
                'sub_cluster_id' => $validated['sub_cluster_id'],
                'nomor_surat' => $validated['nomor_surat'],
                'tanggal_surat' => $validated['tanggal_surat'],
                'perihal_surat' => $validated['perihal_surat'],
                'dokumen' => $dokumenPath,
                'status' => 'draft',
            ]);

            LogActivity::create([
                'user_id' => Auth::id(),
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_record',
                'record_key' => $record->id_ragab,
                'action' => 'create',
                'description' => 'User membuat perekaman surat RAGAB Dewas.',
                'old_values' => null,
                'new_values' => $record->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('ragab.perekaman')
            ->with('success', 'Perekaman surat RAGAB berhasil disimpan.');
    }

    public function storeButir(Request $request, RagabRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah butir RAGAB.');
        }

        $validated = $request->validate([
            'butir_ragab' => ['required', 'string'],

            'unit_kerja_utama_id' => ['required', 'integer'],

            'unit_kerja_pendukung_id' => ['nullable', 'array'],
            'unit_kerja_pendukung_id.*' => ['nullable', 'integer'],

            'komite_id' => ['required', 'integer'],
        ]);

        DB::connection('mysql_ragab')->transaction(function () use ($request, $validated, $record) {
            $butir = RagabButir::create([
                'id_ragab' => $record->id_ragab,
                'butir_ragab' => $validated['butir_ragab'],
            ]);

            RagabButirPic::create([
                'id_butir_ragab' => $butir->id_butir_ragab,
                'unit_kerja_id' => $validated['unit_kerja_utama_id'],
                'komite_id' => null,
                'jenis_pic' => 'utama',
            ]);

            foreach (($validated['unit_kerja_pendukung_id'] ?? []) as $unitKerjaPendukungId) {
                if (!empty($unitKerjaPendukungId)) {
                    RagabButirPic::create([
                        'id_butir_ragab' => $butir->id_butir_ragab,
                        'unit_kerja_id' => $unitKerjaPendukungId,
                        'komite_id' => null,
                        'jenis_pic' => 'pendukung',
                    ]);
                }
            }

            RagabButirPic::create([
                'id_butir_ragab' => $butir->id_butir_ragab,
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
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_butir_ragab',
                'record_key' => $butir->id_butir_ragab,
                'action' => 'create',
                'description' => 'User menambahkan butir RAGAB pada surat ' . $record->id_ragab . '.',
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
            ->route('ragab.perekaman')
            ->with('success', 'Butir RAGAB berhasil ditambahkan.');
    }

    public function downloadDokumen(RagabRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRagabPerekaman()) {
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

    public function requestDelete(Request $request, RagabRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canRequestDeleteRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus perekaman Ragab.');
        }

        // Super admin langsung hapus
        if ($user->isSuperAdmin()) {
            DB::connection('mysql_ragab')->transaction(function () use ($request, $record, $user) {
                $oldData = $record->load([
                    'butirRagab.butirPics',
                    'cluster',
                    'subCluster',
                ])->toArray();

                $recordKey = $record->id_ragab;

                if ($record->dokumen && Storage::disk('public')->exists($record->dokumen)) {
                    Storage::disk('public')->delete($record->dokumen);
                }

                $record->delete();

                LogActivity::create([
                    'user_id' => $user->id,
                    'type_code' => 'ragab',
                    'database_name' => 'sidewas_ragab',
                    'table_name' => 'tb_record',
                    'record_key' => $recordKey,
                    'action' => 'delete',
                    'description' => 'Super Admin menghapus perekaman Ragab secara langsung.',
                    'old_values' => $oldData,
                    'new_values' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            return redirect()
                ->route('ragab.perekaman')
                ->with('success', 'Perekaman Ragab berhasil dihapus.');
        }

        // Cegah request duplicate
        $existingRequest = DeleteRequest::where('type_code', 'ragab')
            ->where('table_name', 'tb_record')
            ->where('record_key', $record->id_ragab)
            ->whereIn('status', [
                'pending_admin_verification',
                'pending_super_admin_approval',
            ])
            ->first();

        if ($existingRequest) {
            return redirect()
                ->route('ragab.perekaman')
                ->with('error', 'Pengajuan hapus untuk data ini masih menunggu proses approval.');
        }

        $status = $user->isRagabModerator()
            ? 'pending_admin_verification'
            : 'pending_super_admin_approval';

        DeleteRequest::create([
            'type_code' => 'ragab',
            'database_name' => 'sidewas_ragab',
            'table_name' => 'tb_record',
            'record_key' => $record->id_ragab,
            'record_label' => $record->id_ragab . ' - ' . $record->nomor_surat,
            'reason' => $request->input('reason'),
            'requested_by' => $user->id,
            'status' => $status,
            'requested_at' => now(),
        ]);

        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => 'ragab',
            'database_name' => 'sidewas_ragab',
            'table_name' => 'tb_record',
            'record_key' => $record->id_ragab,
            'action' => 'request_delete',
            'description' => 'User mengajukan penghapusan perekaman Ragab.',
            'old_values' => $record->toArray(),
            'new_values' => [
                'status_request' => $status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('ragab.perekaman')
            ->with('success', 'Pengajuan hapus berhasil dikirim.');
    }
}
