<?php

namespace App\Http\Controllers\Rawas;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RawasButir;
use App\Models\RawasButirPic;
use App\Models\RawasCluster;
use App\Models\RawasRecord;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PerekamanRawasController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses ke halaman perekaman RAWAS.');
        }

        $recordsQuery = RawasRecord::query()
            ->with([
                'cluster',
                'subCluster',
                'creator',
                'butirRawas.butirPics.unitKerja.direktorat',
                'butirRawas.butirPics.komite',
            ])
            ->withcount('butirRawas');

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
                $query->where('id_rawas', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirRawas', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_rawas', 'like', "%{$keyword}%")
                            ->orWhere('butir_rawas', 'like', "%{$keyword}%");
                    });
            });
        }

        if ($request->filled('direktorat_id')) {
            $unitKerjaIds = UnitKerja::whereHas('direktorat', $request->direktorat_id)
                ->pluck('id')
                ->toArray();

            $recordsQuery->whereIn('unit_kerja_id', function ($query) use ($unitKerjaIds) {
                $query->where('jenis_pic', 'utama')
                    ->whereIn('unit_kerja_id', $unitKerjaIds);
            });
        }

        if ($request->filled('unit_kerja_utama_id')) {
            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'utama')
                    ->where('unit_kerja_id', $request->unit_kerja_utama_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'pendukung')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $recordsQuery->whereHas('butirRawas.butirPics', function ($query) use ($request) {
                $query->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = RawasCluster::with('subClusters')
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
            'total' => RawasRecord::count(),
            'selesai' => RawasRecord::where('status', 'selesai')->count(),
            'proses' => RawasRecord::where('status', 'dalam_proses')->count(),
            'draft' => RawasRecord::where('status', 'draft')->count(),
        ];

        return view('layouts.rawas.perekaman', compact(
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

        if (!$user || !$user->canCreateRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah perekaman RAWAS.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'perihal_surat' => ['required', 'string'],
            'cluster_id' => ['required', 'integer'],
            'sub_cluster_id' => ['required', 'integer'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated) {
            $dokumenPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/record-rawas', 'public');
            }

            $record = RawasRecord::create([
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
                'type_code' => 'rawas',
                'database_name' => 'sidewas_rawas',
                'table_name' => 'tb_record',
                'record_key' => $record->id_rawas,
                'action' => 'create',
                'description' => 'User membuat perekaman surat RAWAS Dewas.',
                'old_values' => null,
                'new_values' => $record->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('rawas.perekaman')
            ->with('success', 'Perekaman surat RAWAS berhasil disimpan.');
    }

    public function storeButir(Request $request, RawasRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah butir RAWAS.');
        }

        $validated = $request->validate([
            'butir_rawas' => ['required', 'string'],

            'unit_kerja_utama_id' => ['required', 'integer'],

            'unit_kerja_pendukung_id' => ['nullable', 'array'],
            'unit_kerja_pendukung_id.*' => ['nullable', 'integer'],

            'komite_id' => ['required', 'integer'],
        ]);

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated, $record) {
            $butir = RawasButir::create([
                'id_rawas' => $record->id_rawas,
                'butir_rawas' => $validated['butir_rawas'],
            ]);

            RawasButirPic::create([
                'id_butir_rawas' => $butir->id_butir_rawas,
                'unit_kerja_id' => $validated['unit_kerja_utama_id'],
                'komite_id' => null,
                'jenis_pic' => 'utama',
            ]);

            foreach (($validated['unit_kerja_pendukung_id'] ?? []) as $unitKerjaPendukungId) {
                if (!empty($unitKerjaPendukungId)) {
                    RawasButirPic::create([
                        'id_butir_rawas' => $butir->id_butir_rawas,
                        'unit_kerja_id' => $unitKerjaPendukungId,
                        'komite_id' => null,
                        'jenis_pic' => 'pendukung',
                    ]);
                }
            }

            RawasButirPic::create([
                'id_butir_rawas' => $butir->id_butir_rawas,
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
                'type_code' => 'rawas',
                'database_name' => 'sidewas_rawas',
                'table_name' => 'tb_butir_rawas',
                'record_key' => $butir->id_butir_rawas,
                'action' => 'create',
                'description' => 'User menambahkan butir RAWAS pada surat ' . $record->id_rawas . '.',
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
            ->route('rawas.perekaman')
            ->with('success', 'Butir RAWAS berhasil ditambahkan.');
    }

    public function downloadDokumen(RawasRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
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

    public function requestDelete(Request $request, RawasRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canRequestDeleteRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus perekaman Rawas.');
        }

        // Super admin langsung hapus
        if ($user->isSuperAdmin()) {
            DB::connection('mysql_rawas')->transaction(function () use ($request, $record, $user) {
                $oldData = $record->load([
                    'butirRawas.butirPics',
                    'cluster',
                    'subCluster',
                ])->toArray();

                $recordKey = $record->id_rawas;

                if ($record->dokumen && Storage::disk('public')->exists($record->dokumen)) {
                    Storage::disk('public')->delete($record->dokumen);
                }

                $record->delete();

                LogActivity::create([
                    'user_id' => $user->id,
                    'type_code' => 'rawas',
                    'database_name' => 'sidewas_rawas',
                    'table_name' => 'tb_record',
                    'record_key' => $recordKey,
                    'action' => 'delete',
                    'description' => 'Super Admin menghapus perekaman Rawas secara langsung.',
                    'old_values' => $oldData,
                    'new_values' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            return redirect()
                ->route('rawas.perekaman')
                ->with('success', 'Perekaman rawas berhasil dihapus.');
        }

        // Cegah request duplicate
        $existingRequest = DeleteRequest::where('type_code', 'rawas')
            ->where('table_name', 'tb_record')
            ->where('record_key', $record->id_rawas)
            ->whereIn('status', [
                'pending_admin_verification',
                'pending_super_admin_approval',
            ])
            ->first();

        if ($existingRequest) {
            return redirect()
                ->route('rawas.perekaman')
                ->with('error', 'Pengajuan hapus untuk data ini masih menunggu proses approval.');
        }

        $status = $user->isRawasModerator()
            ? 'pending_admin_verification'
            : 'pending_super_admin_approval';

        DeleteRequest::create([
            'type_code' => 'rawas',
            'database_name' => 'sidewas_rawas',
            'table_name' => 'tb_record',
            'record_key' => $record->id_rawas,
            'record_label' => $record->id_rawas . ' - ' . $record->nomor_surat,
            'reason' => $request->input('reason'),
            'requested_by' => $user->id,
            'status' => $status,
            'requested_at' => now(),
        ]);

        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => 'rawas',
            'database_name' => 'sidewas_rawas',
            'table_name' => 'tb_record',
            'record_key' => $record->id_rawas,
            'action' => 'request_delete',
            'description' => 'User mengajukan penghapusan perekaman Rawas.',
            'old_values' => $record->toArray(),
            'new_values' => [
                'status_request' => $status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('rawas.perekaman')
            ->with('success', 'Pengajuan hapus berhasil dikirim.');
    }
}
