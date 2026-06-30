<?php

namespace App\Http\Controllers\Ragab;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RagabButir;
use App\Models\RagabButirDirektorat;
use App\Models\RagabButirPic;
use App\Models\RagabButirSubCluster;
use App\Models\RagabCluster;
use App\Models\RagabRecord;
use App\Models\RagabSubCluster;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
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

        $recordsQuery = RagabRecord::with([
            'creator',
            'butirRagab.cluster',
            'butirRagab.subCluster',
            'butirRagab.subClusters',
            'butirRagab.butirPics.unitKerja.direktorat',
            'butirRagab.butirPics.komite',
            'butirRagab.butirDirektorats.direktorat',
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
            $recordsQuery->whereHas('butirRagab', function ($butirQuery) use ($request) {
                $butirQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $recordsQuery->whereHas('butirRagab', function ($butirQuery) use ($request) {
                $butirQuery
                    ->where('sub_cluster_id', $request->sub_cluster_id)
                    ->orWhereHas('subClusters', function ($subClusterQuery) use ($request) {
                        $subClusterQuery->where('tb_sub_cluster.id', $request->sub_cluster_id);
                    });
            });
        }

        if ($request->filled('direktorat_id')) {
            $recordsQuery->whereHas('butirRagab.butirDirektorats', function ($direktoratQuery) use ($request) {
                $direktoratQuery->where('direktorat_id', $request->direktorat_id);
            });
        }

        if ($request->filled('unit_kerja_pendukung_id')) {
            $recordsQuery->whereHas('butirRagab.butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'unit')
                    ->where('unit_kerja_id', $request->unit_kerja_pendukung_id);
            });
        }

        if ($request->filled('komite_id')) {
            $recordsQuery->whereHas('butirRagab.butirPics', function ($picQuery) use ($request) {
                $picQuery->where('jenis_pic', 'komite')
                    ->where('komite_id', $request->komite_id);
            });
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_ragab', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirRagab', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_ragab', 'like', "%{$keyword}%")
                            ->orWhere('agenda_ragab', 'like', "%{$keyword}%")
                            ->orWhere('keputusan_ragab', 'like', "%{$keyword}%");
                    });
            });
        }

        $records = $recordsQuery
            ->orderByDesc('id')
            ->paginate(5)
            ->withQueryString();

        $statistik = [
            'total' => RagabRecord::count(),
            'draft' => RagabRecord::where('status', 'draft')->count(),
            'dalam_proses' => RagabRecord::where('status', 'dalam_proses')->count(),
            'tuntas' => RagabRecord::where('status', 'tuntas')->count(),
        ];

        $clusters = RagabCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $direktorats = Direktorat::orderBy('nama_direktorat')
            ->get();

        $unitKerjas = UnitKerja::with('direktorat')
            ->orderBy('kode_unit')
            ->get();

        $komites = Komite::orderBy('kode_komite')
            ->get();

        $statusOptions = [
            'draft' => 'Draft',
            'dalam_proses' => 'Dalam Proses',
            'tuntas' => 'Tuntas',
        ];

        return view('layouts.ragab.perekaman', compact(
            'records',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'statusOptions',
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
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'dokumen_memo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_ragab')->transaction(function () use ($request, $validated, $user) {
            $dokumenPath = null;
            $dokumenMemoPath = null;

            if ($request->hasFile('dokumen')) {
                $dokumenPath = $request->file('dokumen')->store('dokumen/record-ragab', 'public');
            }

            if ($request->hasFile('dokumen_memo')) {
                $dokumenMemoPath = $request->file('dokumen_memo')->store('dokumen/memo-ragab', 'public');
            }

            $record = RagabRecord::create([
                'nomor_surat' => $validated['nomor_surat'],
                'tanggal_surat' => $validated['tanggal_surat'],
                'perihal_surat' => $validated['perihal_surat'],
                'dokumen' => $dokumenPath,
                'dokumen_memo' => $dokumenMemoPath,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_record',
                'record_key' => $record->id_ragab,
                'action' => 'create',
                'description' => 'User membuat perekaman surat RAGAB.',
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

        if ($record->isButirAdditionLocked()) {
            return back()
                ->withInput()
                ->withErrors([
                    'keputusan_ragab' => 'Butir tidak dapat ditambah karena satu-satunya butir pada surat ini sudah selesai tuntas.',
                ]);
        }

        $validated = $request->validate([
            'cluster_id' => ['required', 'integer', 'exists:mysql_ragab.tb_cluster,id'],
            'sub_cluster_ids' => ['required', 'array', 'min:1'],
            'sub_cluster_ids.*' => ['integer', 'exists:mysql_ragab.tb_sub_cluster,id'],

            'tanggal_ragab' => ['required', 'date'],
            'agenda_ragab' => ['required', 'string'],
            'keputusan_ragab' => ['required', 'string'],

            'direktorat_ids' => ['required', 'array', 'min:1'],
            'direktorat_ids.*' => ['integer', 'exists:mysql.tb_direktorat,id'],

            'unit_kerja_ids' => ['required', 'array', 'min:1'],
            'unit_kerja_ids.*' => ['integer', 'exists:mysql.tb_unit_kerja,id'],

            'komite_id' => ['nullable', 'integer'],
        ]);

        $selectedSubClusterIds = collect($validated['sub_cluster_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $invalidSubClusterIds = RagabSubCluster::whereIn('id', $selectedSubClusterIds)
            ->where('cluster_id', '!=', $validated['cluster_id'])
            ->pluck('nama_sub_cluster');

        if ($invalidSubClusterIds->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'sub_cluster_ids' => 'Sub-cluster berikut tidak sesuai dengan cluster yang dipilih: ' . $invalidSubClusterIds->implode(', '),
                ]);
        }

        $selectedDirektoratIds = collect($validated['direktorat_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $selectedUnitKerjaIds = collect($validated['unit_kerja_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $unitKerjasByDirektorat = UnitKerja::whereIn('id', $selectedUnitKerjaIds)
            ->whereNotNull('direktorat_id')
            ->get()
            ->groupBy('direktorat_id');

        $missingDirektoratIds = $selectedDirektoratIds
            ->filter(function ($direktoratId) use ($unitKerjasByDirektorat) {
                return ! $unitKerjasByDirektorat->has($direktoratId);
            })
            ->values();

        if ($missingDirektoratIds->isNotEmpty()) {
            $missingDirektoratNames = Direktorat::whereIn('id', $missingDirektoratIds)
                ->pluck('nama_direktorat')
                ->implode(', ');

            return back()
                ->withInput()
                ->withErrors([
                    'unit_kerja_ids' => 'Setiap direktorat yang dipilih wajib memiliki minimal 1 PIC Unit. Direktorat yang belum memiliki PIC Unit: ' . $missingDirektoratNames,
                ]);
        }

        DB::connection('mysql_ragab')->transaction(function () use ($request, $validated, $record, $selectedSubClusterIds, $user) {
            $butir = RagabButir::create([
                'id_ragab' => $record->id_ragab,
                'cluster_id' => $validated['cluster_id'],
                'sub_cluster_id' => $selectedSubClusterIds->first(),
                'tanggal_ragab' => $validated['tanggal_ragab'],
                'agenda_ragab' => $validated['agenda_ragab'],
                'keputusan_ragab' => $validated['keputusan_ragab'],
                'status' => 'terbit',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($selectedSubClusterIds as $subClusterId) {
                RagabButirSubCluster::create([
                    'id_butir_ragab' => $butir->id_butir_ragab,
                    'sub_cluster_id' => $subClusterId,
                ]);
            }

            foreach (array_unique($validated['direktorat_ids']) as $direktoratId) {
                RagabButirDirektorat::create([
                    'id_butir_ragab' => $butir->id_butir_ragab,
                    'direktorat_id' => $direktoratId,
                ]);
            }

            foreach (array_unique($validated['unit_kerja_ids']) as $unitKerjaId) {
                RagabButirPic::create([
                    'id_butir_ragab' => $butir->id_butir_ragab,
                    'unit_kerja_id' => $unitKerjaId,
                    'komite_id' => null,
                    'jenis_pic' => 'unit',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            if (!empty($validated['komite_id'])) {
                RagabButirPic::create([
                    'id_butir_ragab' => $butir->id_butir_ragab,
                    'unit_kerja_id' => null,
                    'komite_id' => $validated['komite_id'],
                    'jenis_pic' => 'komite',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            $record->refresh()->syncStatusFromButir($user->id);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_butir_ragab',
                'record_key' => $butir->id_butir_ragab,
                'action' => 'create',
                'description' => 'User menambah butir keputusan RAGAB.',
                'old_values' => null,
                'new_values' => [
                    'butir' => $butir->load([
                        'record',
                        'cluster',
                        'subCluster',
                        'subClusters',
                        'butirPics',
                        'butirDirektorats',
                    ])->toArray(),
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
        if (!$record->dokumen) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $record->dokumen);

        if (!file_exists($filePath)) {
            abort(404, 'File tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }

    public function update(Request $request, RagabRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit perekaman RAGAB.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string'],
            'tanggal_surat' => ['required', 'date'],
            'perihal_surat' => ['required', 'string'],
            'status' => ['required', 'string', 'in:draft,dalam_proses,tuntas'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
            'dokumen_memo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],

            'butir_id' => ['required', 'integer', 'exists:mysql_ragab.tb_butir_ragab,id'],
            'butir_status' => ['required', 'string', 'in:terbit,dalam_proses,diusulkan_tuntas,selesai_tuntas'],
            'cluster_id' => ['required', 'integer', 'exists:mysql_ragab.tb_cluster,id'],
            'sub_cluster_ids' => ['required', 'array', 'min:1'],
            'sub_cluster_ids.*' => ['required', 'integer', 'exists:mysql_ragab.tb_sub_cluster,id'],
            'tanggal_ragab' => ['required', 'date'],
            'agenda_ragab' => ['required', 'string'],
            'keputusan_ragab' => ['required', 'string'],
            'direktorat_ids' => ['required', 'array', 'min:1'],
            'direktorat_ids.*' => ['required', 'integer', 'exists:mysql.tb_direktorat,id'],
            'unit_kerja_ids' => ['required', 'array', 'min:1'],
            'unit_kerja_ids.*' => ['required', 'integer', 'exists:mysql.tb_unit_kerja,id'],
            'komite_id' => ['nullable', 'integer', 'exists:mysql.tb_komite,id'],
        ]);

        $selectedSubClusterIds = collect($validated['sub_cluster_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $invalidSubClusterNames = RagabSubCluster::whereIn('id', $selectedSubClusterIds)
            ->where('cluster_id', '!=', $validated['cluster_id'])
            ->pluck('nama_sub_cluster');

        if ($invalidSubClusterNames->isNotEmpty()) {
            return back()->withInput()->withErrors([
                'sub_cluster_ids' => 'Sub-cluster berikut tidak sesuai dengan cluster yang dipilih: ' . $invalidSubClusterNames->implode(', '),
            ]);
        }

        $selectedDirektoratIds = collect($validated['direktorat_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $selectedUnitKerjaIds = collect($validated['unit_kerja_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $unitKerjasByDirektorat = UnitKerja::whereIn('id', $selectedUnitKerjaIds)
            ->whereNotNull('direktorat_id')
            ->get()
            ->groupBy('direktorat_id');

        $missingDirektoratIds = $selectedDirektoratIds
            ->filter(fn ($direktoratId) => ! $unitKerjasByDirektorat->has($direktoratId))
            ->values();

        if ($missingDirektoratIds->isNotEmpty()) {
            $missingDirektoratNames = Direktorat::whereIn('id', $missingDirektoratIds)
                ->pluck('nama_direktorat')
                ->implode(', ');

            return back()->withInput()->withErrors([
                'unit_kerja_ids' => 'Setiap direktorat yang dipilih wajib memiliki minimal 1 PIC Unit. Direktorat yang belum memiliki PIC Unit: ' . $missingDirektoratNames,
            ]);
        }

        $butir = $record->butirRagab()->where('id', $validated['butir_id'])->firstOrFail();

        if (! $user->isSuperAdmin()) {
            $existingRequest = DeleteRequest::where('type_code', 'ragab')
                ->where('table_name', 'tb_record')
                ->where('record_key', $record->id_ragab)
                ->where('reason', 'like', '%"action":"update_ragab_perekaman"%')
                ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
                ->first();

            if ($existingRequest) {
                return redirect()->route('ragab.perekaman')->with('error', 'Pengajuan untuk data ini masih menunggu proses approval.');
            }
        }

        $payload = $this->buildRagabPerekamanUpdatePayload($request, $validated, $butir, $selectedSubClusterIds, $selectedDirektoratIds, $selectedUnitKerjaIds);

        if ($user->isSuperAdmin()) {
            $this->applyRagabPerekamanUpdate($record, $payload, $user, $request);

            return redirect()->route('ragab.perekaman')->with('success', 'Perekaman RAGAB berhasil diperbarui.');
        }

        $status = $user->isRagabModerator() ? 'pending_admin_verification' : 'pending_super_admin_approval';

        DeleteRequest::create([
            'type_code' => 'ragab',
            'database_name' => 'sidewas_ragab',
            'table_name' => 'tb_record',
            'record_key' => $record->id_ragab,
            'record_label' => $record->id_ragab . ' - ' . $record->nomor_surat,
            'reason' => json_encode([
                'action' => 'update_ragab_perekaman',
                'payload' => $payload,
            ]),
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
            'action' => 'request_update',
            'description' => 'User mengajukan edit perekaman RAGAB.',
            'old_values' => $record->load(['butirRagab.butirPics', 'butirRagab.butirDirektorats', 'butirRagab.subClusters'])->toArray(),
            'new_values' => [
                'status_request' => $status,
                'payload' => $payload,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('ragab.perekaman')->with('success', 'Pengajuan edit berhasil dikirim.');
    }

    private function buildRagabPerekamanUpdatePayload(Request $request, array $validated, RagabButir $butir, $subClusterIds, $direktoratIds, $unitKerjaIds): array
    {
        $payload = [
            'record' => [
                'nomor_surat' => $validated['nomor_surat'],
                'tanggal_surat' => $validated['tanggal_surat'],
                'perihal_surat' => $validated['perihal_surat'],
                'status' => $validated['status'],
            ],
            'butir' => [
                'id' => (int) $butir->id,
                'id_butir_ragab' => $butir->id_butir_ragab,
                'status' => $validated['butir_status'],
                'cluster_id' => (int) $validated['cluster_id'],
                'sub_cluster_ids' => $subClusterIds->all(),
                'tanggal_ragab' => $validated['tanggal_ragab'],
                'agenda_ragab' => $validated['agenda_ragab'],
                'keputusan_ragab' => $validated['keputusan_ragab'],
                'direktorat_ids' => $direktoratIds->all(),
                'unit_kerja_ids' => $unitKerjaIds->all(),
                'komite_id' => ! empty($validated['komite_id']) ? (int) $validated['komite_id'] : null,
            ],
            'files' => [],
        ];

        foreach (['dokumen', 'dokumen_memo'] as $fileField) {
            if ($request->hasFile($fileField)) {
                $payload['files'][$fileField] = [
                    'path' => $request->file($fileField)->store('dokumen/pending-edit-ragab', 'public'),
                    'original_name' => $request->file($fileField)->getClientOriginalName(),
                ];
            }
        }

        return $payload;
    }

    public function applyRagabPerekamanUpdate(RagabRecord $record, array $payload, User $user, Request $request): void
    {
        DB::connection('mysql_ragab')->transaction(function () use ($record, $payload, $user, $request) {
            $oldValues = $record->load(['butirRagab.butirPics', 'butirRagab.butirDirektorats', 'butirRagab.subClusters'])->toArray();
            $recordPayload = $payload['record'] ?? [];
            $butirPayload = $payload['butir'] ?? [];
            $filePayload = $payload['files'] ?? [];

            $recordUpdates = [
                'nomor_surat' => $recordPayload['nomor_surat'] ?? $record->nomor_surat,
                'tanggal_surat' => $recordPayload['tanggal_surat'] ?? $record->tanggal_surat,
                'perihal_surat' => $recordPayload['perihal_surat'] ?? $record->perihal_surat,
                'status' => $recordPayload['status'] ?? $record->status,
                'updated_by' => $user->id,
            ];

            if (!empty($recordUpdates['tanggal_surat'])) {
                $recordUpdates['jth_tempo'] = Carbon::parse($recordUpdates['tanggal_surat'])->addDays(30);
            }

            foreach (['dokumen', 'dokumen_memo'] as $fileField) {
                if (!empty($filePayload[$fileField]['path'])) {
                    if ($record->{$fileField} && Storage::disk('public')->exists($record->{$fileField})) {
                        Storage::disk('public')->delete($record->{$fileField});
                    }

                    $recordUpdates[$fileField] = $filePayload[$fileField]['path'];
                }
            }

            $record->update($recordUpdates);

            if (!empty($butirPayload['id'])) {
                $butir = $record->butirRagab()->where('id', (int) $butirPayload['id'])->firstOrFail();
                $subClusterIds = collect($butirPayload['sub_cluster_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

                $butir->update([
                    'cluster_id' => $butirPayload['cluster_id'],
                    'sub_cluster_id' => $subClusterIds->first(),
                    'tanggal_ragab' => $butirPayload['tanggal_ragab'],
                    'agenda_ragab' => $butirPayload['agenda_ragab'],
                    'keputusan_ragab' => $butirPayload['keputusan_ragab'],
                    'status' => $butirPayload['status'],
                    'updated_by' => $user->id,
                ]);

                RagabButirSubCluster::where('id_butir_ragab', $butir->id_butir_ragab)->delete();
                foreach ($subClusterIds as $subClusterId) {
                    RagabButirSubCluster::create([
                        'id_butir_ragab' => $butir->id_butir_ragab,
                        'sub_cluster_id' => $subClusterId,
                    ]);
                }

                RagabButirDirektorat::where('id_butir_ragab', $butir->id_butir_ragab)->delete();
                foreach (($butirPayload['direktorat_ids'] ?? []) as $direktoratId) {
                    RagabButirDirektorat::create([
                        'id_butir_ragab' => $butir->id_butir_ragab,
                        'direktorat_id' => (int) $direktoratId,
                    ]);
                }

                $butir->butirPics()->delete();
                foreach (($butirPayload['unit_kerja_ids'] ?? []) as $unitKerjaId) {
                    RagabButirPic::create([
                        'id_butir_ragab' => $butir->id_butir_ragab,
                        'unit_kerja_id' => (int) $unitKerjaId,
                        'komite_id' => null,
                        'jenis_pic' => 'unit',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }

                if (!empty($butirPayload['komite_id'])) {
                    RagabButirPic::create([
                        'id_butir_ragab' => $butir->id_butir_ragab,
                        'unit_kerja_id' => null,
                        'komite_id' => (int) $butirPayload['komite_id'],
                        'jenis_pic' => 'komite',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            $record->refresh()->syncStatusFromButir($user->id);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'ragab',
                'database_name' => 'sidewas_ragab',
                'table_name' => 'tb_record',
                'record_key' => $record->id_ragab,
                'action' => 'update',
                'description' => 'User memperbarui perekaman RAGAB.',
                'old_values' => $oldValues,
                'new_values' => $record->fresh()->load(['butirRagab.butirPics', 'butirRagab.butirDirektorats', 'butirRagab.subClusters'])->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });
    }

    public function downloadDokumenMemo(RagabRecord $record)
    {
        if (!$record->dokumen_memo) {
            abort(404, 'Dokumen memo tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $record->dokumen_memo);

        if (!file_exists($filePath)) {
            abort(404, 'File memo tidak ditemukan di storage.');
        }

        return response()->download($filePath);
    }

    public function requestDelete(Request $request, RagabRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canRequestDeleteRagabPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus perekaman RAGAB.');
        }

        // Super admin langsung hapus
        if ($user->isSuperAdmin()) {
            DB::connection('mysql_ragab')->transaction(function () use ($request, $record, $user) {
                $oldData = $record->load([
                    'butirragab.butirPics',
                    'butirragab.cluster',
                    'butirragab.subCluster',
                    'butirragab.subClusters',
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
