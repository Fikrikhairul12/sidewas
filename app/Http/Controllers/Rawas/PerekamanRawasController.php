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
use App\Models\RawasSubCluster;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
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
                'creator',
                'butirRawas.cluster',
                'butirRawas.subCluster',
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
            $recordsQuery->whereHas('butirRawas', function ($butirQuery) use ($request) {
                $butirQuery->where('cluster_id', $request->cluster_id);
            });
        }

        if ($request->filled('sub_cluster_id')) {
            $recordsQuery->whereHas('butirRawas', function ($butirQuery) use ($request) {
                $butirQuery->where('sub_cluster_id', $request->sub_cluster_id);
            });
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $recordsQuery->where(function ($query) use ($keyword) {
                $query->where('id_rawas', 'like', "%{$keyword}%")
                    ->orWhere('nomor_surat', 'like', "%{$keyword}%")
                    ->orWhere('perihal_surat', 'like', "%{$keyword}%")
                    ->orWhereHas('butirRawas', function ($butirQuery) use ($keyword) {
                        $butirQuery->where('id_butir_rawas', 'like', "%{$keyword}%")
                            ->orWhere('agenda_rawas', 'like', "%{$keyword}%")
                            ->orWhere('keputusan_rawas', 'like', "%{$keyword}%");
                    });
            });
        }

        $records = $recordsQuery
            ->latest()
            ->paginate(2)
            ->withQueryString();

        $clusters = RawasCluster::with('subClusters')
            ->orderBy('nama_cluster')
            ->get();

        $dewasDirektorat = Direktorat::where('nama_direktorat', 'like', '%Dewan Pengawas%')
            ->first();

        $unitKerjas = UnitKerja::query()
            ->orderBy('nama_unit')
            ->get();

        $komites = Komite::orderBy('nama_komite')->get();

        $picOptions = collect()
            ->merge(
                $unitKerjas->map(function ($unit) {
                    return [
                        'value' => 'unit:' . $unit->id,
                        'label' => ($unit->kode_unit ?? '-') . ' - ' . $unit->nama_unit,
                        'sub_label' => 'Dewan Pengawas',
                        'type' => 'Direktorat',
                    ];
                })
            )
            ->merge(
                $komites->map(function ($komite) {
                    return [
                        'value' => 'komite:' . $komite->id,
                        'label' => ($komite->kode_komite ?? '-') . ' - ' . $komite->nama_komite,
                        'sub_label' => 'Dewan Pengawas',
                        'type' => 'Direktorat',
                    ];
                })
            )
            ->values();

        $direktorats = $dewasDirektorat ? collect([$dewasDirektorat]) : collect();

        $statistik = [
            'total' => RawasRecord::count(),
            'draft' => RawasRecord::where('status', 'draft')->count(),
            'dalam_proses' => RawasRecord::where('status', 'dalam_proses')->count(),
            'tuntas' => RawasRecord::where('status', 'tuntas')->count(),
        ];

        return view('layouts.rawas.perekaman', compact(
            'records',
            'clusters',
            'direktorats',
            'unitKerjas',
            'komites',
            'picOptions',
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
            'dokumen_memo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],
        ]);

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated) {
            $dokumenMemoPath = null;

            if ($request->hasFile('dokumen_memo')) {
                $dokumenMemoPath = $request->file('dokumen_memo')->store('dokumen/memo-rawas', 'public');
            }

            $record = RawasRecord::create([
                'nomor_surat' => $validated['nomor_surat'],
                'tanggal_surat' => $validated['tanggal_surat'],
                'perihal_surat' => $validated['perihal_surat'],
                'dokumen_memo' => $dokumenMemoPath,
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

        if ($record->isButirAdditionLocked()) {
            return back()
                ->withInput()
                ->withErrors([
                    'keputusan_rawas' => 'Butir tidak dapat ditambah karena satu-satunya butir pada surat ini sudah selesai tuntas.',
                ]);
        }

        $validated = $request->validate([
            'cluster_id' => ['required', 'integer', 'exists:mysql_rawas.tb_cluster,id'],
            'sub_cluster_id' => ['required', 'integer', 'exists:mysql_rawas.tb_sub_cluster,id'],
            'tanggal_rawas' => ['required', 'date'],
            'agenda_rawas' => ['required', 'string'],
            'keputusan_rawas' => ['required', 'string'],
            'pic_ids' => ['required', 'array', 'min:1'],
            'pic_ids.*' => ['required', 'string'],
        ]);

        DB::connection('mysql_rawas')->transaction(function () use ($request, $validated, $record) {
            $butir = RawasButir::create([
                'id_rawas' => $record->id_rawas,
                'cluster_id' => $validated['cluster_id'],
                'sub_cluster_id' => $validated['sub_cluster_id'],
                'tanggal_rawas' => $validated['tanggal_rawas'],
                'agenda_rawas' => $validated['agenda_rawas'],
                'keputusan_rawas' => $validated['keputusan_rawas'],
                'status' => 'terbit',
            ]);

            foreach ($validated['pic_ids'] as $picValue) {
                if (!str_contains($picValue, ':')) {
                    continue;
                }

                [$jenisPic, $picId] = explode(':', $picValue, 2);

                if ($jenisPic === 'unit') {
                    RawasButirPic::firstOrCreate([
                        'id_butir_rawas' => $butir->id_butir_rawas,
                        'unit_kerja_id' => (int) $picId,
                        'komite_id' => null,
                        'jenis_pic' => 'unit',
                    ]);
                }

                if ($jenisPic === 'komite') {
                    RawasButirPic::firstOrCreate([
                        'id_butir_rawas' => $butir->id_butir_rawas,
                        'unit_kerja_id' => null,
                        'komite_id' => (int) $picId,
                        'jenis_pic' => 'komite',
                    ]);
                }
            }

            $record->refresh()->syncStatusFromButir(Auth::id());

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
        return $this->downloadDokumenMemo($record);
    }

    public function update(Request $request, RawasRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canCreateRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit perekaman RAWAS.');
        }

        $validated = $request->validate([
            'nomor_surat' => ['required', 'string'],
            'tanggal_surat' => ['required', 'date'],
            'perihal_surat' => ['required', 'string'],
            'status' => ['required', 'string', 'in:draft,dalam_proses,tuntas'],
            'dokumen_memo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:5120'],

            'butir_id' => ['required', 'integer', 'exists:mysql_rawas.tb_butir_rawas,id'],
            'butir_status' => ['required', 'string', 'in:terbit,dalam_proses,diusulkan_tuntas,selesai_tuntas'],
            'cluster_id' => ['required', 'integer', 'exists:mysql_rawas.tb_cluster,id'],
            'sub_cluster_id' => ['required', 'integer', 'exists:mysql_rawas.tb_sub_cluster,id'],
            'tanggal_rawas' => ['required', 'date'],
            'agenda_rawas' => ['required', 'string'],
            'keputusan_rawas' => ['required', 'string'],
            'pic_ids' => ['required', 'array', 'min:1'],
            'pic_ids.*' => ['required', 'string'],
        ]);

        $subClusterBelongsToCluster = RawasSubCluster::where('id', $validated['sub_cluster_id'])
            ->where('cluster_id', $validated['cluster_id'])
            ->exists();

        if (! $subClusterBelongsToCluster) {
            return back()->withInput()->withErrors([
                'sub_cluster_id' => 'Sub-cluster tidak sesuai dengan cluster yang dipilih.',
            ]);
        }

        $butir = $record->butirRawas()->where('id', $validated['butir_id'])->firstOrFail();

        if (! $user->isSuperAdmin()) {
            $existingRequest = DeleteRequest::where('type_code', 'rawas')
                ->where('table_name', 'tb_record')
                ->where('record_key', $record->id_rawas)
                ->where('reason', 'like', '%"action":"update_rawas_perekaman"%')
                ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
                ->first();

            if ($existingRequest) {
                return redirect()->route('rawas.perekaman')->with('error', 'Pengajuan untuk data ini masih menunggu proses approval.');
            }
        }

        $payload = $this->buildRawasPerekamanUpdatePayload($request, $validated, $butir);

        if ($user->isSuperAdmin()) {
            $this->applyRawasPerekamanUpdate($record, $payload, $user, $request);

            return redirect()->route('rawas.perekaman')->with('success', 'Perekaman RAWAS berhasil diperbarui.');
        }

        $status = $user->isRawasModerator() ? 'pending_admin_verification' : 'pending_super_admin_approval';

        DeleteRequest::create([
            'type_code' => 'rawas',
            'database_name' => 'sidewas_rawas',
            'table_name' => 'tb_record',
            'record_key' => $record->id_rawas,
            'record_label' => $record->id_rawas . ' - ' . $record->nomor_surat,
            'reason' => json_encode([
                'action' => 'update_rawas_perekaman',
                'payload' => $payload,
            ]),
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
            'action' => 'request_update',
            'description' => 'User mengajukan edit perekaman RAWAS.',
            'old_values' => $record->load(['butirRawas.butirPics'])->toArray(),
            'new_values' => [
                'status_request' => $status,
                'payload' => $payload,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('rawas.perekaman')->with('success', 'Pengajuan edit berhasil dikirim.');
    }

    private function buildRawasPerekamanUpdatePayload(Request $request, array $validated, RawasButir $butir): array
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
                'id_butir_rawas' => $butir->id_butir_rawas,
                'status' => $validated['butir_status'],
                'cluster_id' => (int) $validated['cluster_id'],
                'sub_cluster_id' => (int) $validated['sub_cluster_id'],
                'tanggal_rawas' => $validated['tanggal_rawas'],
                'agenda_rawas' => $validated['agenda_rawas'],
                'keputusan_rawas' => $validated['keputusan_rawas'],
                'pic_ids' => collect($validated['pic_ids'])->filter()->unique()->values()->all(),
            ],
            'files' => [],
        ];

        if ($request->hasFile('dokumen_memo')) {
            $payload['files']['dokumen_memo'] = [
                'path' => $request->file('dokumen_memo')->store('dokumen/pending-edit-rawas', 'public'),
                'original_name' => $request->file('dokumen_memo')->getClientOriginalName(),
            ];
        }

        return $payload;
    }

    public function applyRawasPerekamanUpdate(RawasRecord $record, array $payload, User $user, Request $request): void
    {
        DB::connection('mysql_rawas')->transaction(function () use ($record, $payload, $user, $request) {
            $oldValues = $record->load(['butirRawas.butirPics'])->toArray();
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

            if (!empty($filePayload['dokumen_memo']['path'])) {
                if ($record->dokumen_memo && Storage::disk('public')->exists($record->dokumen_memo)) {
                    Storage::disk('public')->delete($record->dokumen_memo);
                }

                $recordUpdates['dokumen_memo'] = $filePayload['dokumen_memo']['path'];
            }

            $record->update($recordUpdates);

            if (!empty($butirPayload['id'])) {
                $butir = $record->butirRawas()->where('id', (int) $butirPayload['id'])->firstOrFail();

                $butir->update([
                    'cluster_id' => $butirPayload['cluster_id'],
                    'sub_cluster_id' => $butirPayload['sub_cluster_id'],
                    'tanggal_rawas' => $butirPayload['tanggal_rawas'],
                    'agenda_rawas' => $butirPayload['agenda_rawas'],
                    'keputusan_rawas' => $butirPayload['keputusan_rawas'],
                    'status' => $butirPayload['status'],
                    'updated_by' => $user->id,
                ]);

                $butir->butirPics()->delete();

                foreach (($butirPayload['pic_ids'] ?? []) as $picValue) {
                    if (!str_contains($picValue, ':')) {
                        continue;
                    }

                    [$jenisPic, $picId] = explode(':', $picValue, 2);

                    RawasButirPic::create([
                        'id_butir_rawas' => $butir->id_butir_rawas,
                        'unit_kerja_id' => $jenisPic === 'unit' ? (int) $picId : null,
                        'komite_id' => $jenisPic === 'komite' ? (int) $picId : null,
                        'jenis_pic' => $jenisPic === 'komite' ? 'komite' : 'unit',
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            $record->refresh()->syncStatusFromButir($user->id);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'rawas',
                'database_name' => 'sidewas_rawas',
                'table_name' => 'tb_record',
                'record_key' => $record->id_rawas,
                'action' => 'update',
                'description' => 'User memperbarui perekaman RAWAS.',
                'old_values' => $oldValues,
                'new_values' => $record->fresh()->load(['butirRawas.butirPics'])->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });
    }

    public function downloadDokumenMemo(RawasRecord $record)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessRawasPerekaman()) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen.');
        }

        if (!$record->dokumen_memo) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $record->dokumen_memo);

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

                if ($record->dokumen_memo && Storage::disk('public')->exists($record->dokumen_memo)) {
                    Storage::disk('public')->delete($record->dokumen_memo);
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
