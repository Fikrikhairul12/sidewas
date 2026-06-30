<?php

namespace App\Http\Controllers\ProdukHukum;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\LogActivity;
use App\Models\ProdukHukum;
use App\Models\ProdukHukumFile;
use App\Models\ProdukHukumJenisPeraturan;
use App\Models\ProdukHukumRelasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProdukHukumController extends Controller
{
    public function index(Request $request)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canAccessProdukHukum()) {
            abort(403, 'Anda tidak memiliki akses ke halaman Produk Hukum.');
        }

        $query = ProdukHukum::with(['files', 'relasis.produkHukumTerkait', 'creator'])
            ->withCount('files')
            ->latest();

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;

            $query->where(function ($q) use ($keyword) {
                $q->where('kode_produk_hukum', 'like', "%{$keyword}%")
                    ->orWhere('judul', 'like', "%{$keyword}%")
                    ->orWhere('nomor_peraturan_keputusan', 'like', "%{$keyword}%")
                    ->orWhere('tahun_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('jenis_bentuk_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('singkatan_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('subjek', 'like', "%{$keyword}%")
                    ->orWhere('bidang_pengaturan', 'like', "%{$keyword}%")
                    ->orWhere('keterangan', 'like', "%{$keyword}%")
                    ->orWhere('muatan_substansial', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('bidang_pengaturan')) {
            $query->where('bidang_pengaturan', $request->bidang_pengaturan);
        }

        if ($request->filled('jenis_bentuk_peraturan')) {
            $query->where('jenis_bentuk_peraturan', $request->jenis_bentuk_peraturan);
        }

        if ($request->filled('tahun_peraturan')) {
            $query->where('tahun_peraturan', $request->tahun_peraturan);
        }

        $produkHukums = $query
            ->paginate(8)
            ->withQueryString();

        $pendingAccessIds = DeleteRequest::where('type_code', 'produk_hukum')
            ->where('table_name', 'tb_produk_hukum')
            ->where('requested_by', $user->id)
            ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
            ->where('reason', 'like', '%"action":"view_produk_hukum"%')
            ->pluck('record_key')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $pendingDeleteIds = DeleteRequest::where('type_code', 'produk_hukum')
            ->where('table_name', 'tb_produk_hukum')
            ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
            ->where('reason', 'like', '%"action":"delete_produk_hukum"%')
            ->pluck('record_key')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $approvedAccessIds = $this->approvedRahasiaProdukHukumIds($user);
        $bidangOptions = ProdukHukum::query()
            ->whereNotNull('bidang_pengaturan')
            ->where('bidang_pengaturan', '<>', '')
            ->distinct()
            ->orderBy('bidang_pengaturan')
            ->pluck('bidang_pengaturan');
        $jenisOptions = ProdukHukumJenisPeraturan::query()
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        $tahunOptions = ProdukHukum::query()
            ->whereNotNull('tahun_peraturan')
            ->distinct()
            ->orderByDesc('tahun_peraturan')
            ->pluck('tahun_peraturan');

        return view('layouts.produk-hukum.index', compact(
            'produkHukums',
            'pendingAccessIds',
            'pendingDeleteIds',
            'approvedAccessIds',
            'bidangOptions',
            'jenisOptions',
            'tahunOptions'
        ));
    }

    public function store(Request $request)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canCreateProdukHukum()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah Produk Hukum.');
        }

        $validated = $request->validate([
            'kode_produk_hukum' => ['nullable', 'string', 'max:80', 'unique:mysql_produk_hukum.tb_produk_hukum,kode_produk_hukum'],
            'judul' => ['required', 'string'],
            'nomor_peraturan_keputusan' => ['nullable', 'string', 'max:255'],
            'tahun_peraturan' => ['nullable', 'integer', 'min:1900', 'max:' . (now()->year + 1)],
            'jenis_bentuk_peraturan' => [
                'nullable',
                'string',
                'max:255',
                Rule::exists('mysql_produk_hukum.tb_jenis_peraturan', 'nama')->where('is_active', true),
            ],
            'singkatan_peraturan' => ['nullable', 'string', 'max:50'],
            'tanggal_penetapan' => ['nullable', 'date'],
            'tanggal_diundangkan' => ['nullable', 'date'],
            'sumber_ln_tbn' => ['nullable', 'string', 'max:255'],
            'sumber_tln_tbn' => ['nullable', 'string', 'max:255'],
            'subjek' => ['nullable', 'string'],
            'bidang_pengaturan' => ['nullable', 'string', 'max:255'],
            'abstrak' => ['nullable', 'string'],
            'keterangan' => ['nullable', 'string'],
            'muatan_substansial' => ['nullable', 'string'],
            'status_peraturan' => ['required', Rule::in(['draft', 'berlaku', 'tidak_berlaku'])],
            'sifat_dokumen' => ['required', Rule::in(['publik', 'rahasia'])],
            'bentuk_file' => ['required', Rule::in(['file', 'link'])],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
            'link_file' => ['nullable', 'url', 'max:2048'],
            'nama_link_file' => ['nullable', 'string', 'max:255'],
            'jenis_file' => ['nullable', 'string', 'max:255'],
            'jenis_relasi' => ['nullable', Rule::in(['mencabut', 'dicabut_oleh', 'mengubah', 'diubah_oleh', 'terkait'])],
            'produk_hukum_terkait_id' => ['nullable', 'integer', 'exists:mysql_produk_hukum.tb_produk_hukum,id'],
            'nomor_produk_hukum_terkait' => ['nullable', 'string', 'max:255'],
            'judul_terkait' => ['nullable', 'string'],
            'keterangan_relasi' => ['nullable', 'string'],
        ]);

        DB::connection('mysql_produk_hukum')->transaction(function () use ($request, $validated, $user) {
            $produkHukum = ProdukHukum::create([
                'kode_produk_hukum' => $validated['kode_produk_hukum'] ?? null,
                'judul' => $validated['judul'],
                'nomor_peraturan_keputusan' => $validated['nomor_peraturan_keputusan'] ?? null,
                'tahun_peraturan' => $validated['tahun_peraturan'] ?? null,
                'jenis_bentuk_peraturan' => $validated['jenis_bentuk_peraturan'] ?? null,
                'singkatan_peraturan' => $validated['singkatan_peraturan'] ?? null,
                'tanggal_penetapan' => $validated['tanggal_penetapan'] ?? null,
                'tanggal_diundangkan' => $validated['tanggal_diundangkan'] ?? null,
                'sumber_ln_tbn' => $validated['sumber_ln_tbn'] ?? null,
                'sumber_tln_tbn' => $validated['sumber_tln_tbn'] ?? null,
                'subjek' => $validated['subjek'] ?? null,
                'bidang_pengaturan' => $validated['bidang_pengaturan'] ?? null,
                'abstrak' => $validated['abstrak'] ?? null,
                'keterangan' => $validated['keterangan'] ?? null,
                'muatan_substansial' => $validated['muatan_substansial'] ?? null,
                'status_peraturan' => $validated['status_peraturan'],
                'sifat_dokumen' => $validated['sifat_dokumen'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($request->file('files', []) as $file) {
                $path = $file->store('produk-hukum', 'public');

                ProdukHukumFile::create([
                    'produk_hukum_id' => $produkHukum->id,
                    'bentuk_file' => 'file',
                    'nama_file' => $file->getClientOriginalName(),
                    'path_file' => $path,
                    'link_file' => null,
                    'mime_type' => $file->getMimeType(),
                    'ukuran_file' => $file->getSize(),
                    'jenis_file' => $validated['jenis_file'] ?? 'lampiran',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            if (($validated['bentuk_file'] ?? null) === 'link' && ! empty($validated['link_file'])) {
                ProdukHukumFile::create([
                    'produk_hukum_id' => $produkHukum->id,
                    'bentuk_file' => 'link',
                    'nama_file' => $validated['nama_link_file'] ?: $validated['link_file'],
                    'path_file' => null,
                    'link_file' => $validated['link_file'],
                    'mime_type' => null,
                    'ukuran_file' => null,
                    'jenis_file' => $validated['jenis_file'] ?? 'tautan',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            if (! empty($validated['jenis_relasi'])) {
                ProdukHukumRelasi::create([
                    'produk_hukum_id' => $produkHukum->id,
                    'jenis_relasi' => $validated['jenis_relasi'],
                    'produk_hukum_terkait_id' => $validated['produk_hukum_terkait_id'] ?? null,
                    'nomor_produk_hukum_terkait' => $validated['nomor_produk_hukum_terkait'] ?? null,
                    'judul_terkait' => $validated['judul_terkait'] ?? null,
                    'keterangan' => $validated['keterangan_relasi'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'produk_hukum',
                'database_name' => 'sidewas_produk_hukum',
                'table_name' => 'tb_produk_hukum',
                'record_key' => (string) $produkHukum->id,
                'action' => 'create',
                'description' => 'User menambah Produk Hukum.',
                'old_values' => null,
                'new_values' => $produkHukum->load(['files', 'relasis'])->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });

        return redirect()
            ->route('produk-hukum.index')
            ->with('success', 'Produk Hukum berhasil ditambahkan.');
    }

    public function requestAccess(Request $request, ProdukHukum $produkHukum)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canAccessProdukHukum()) {
            abort(403, 'Anda tidak memiliki akses ke halaman Produk Hukum.');
        }

        if ($produkHukum->sifat_dokumen !== 'rahasia') {
            return back()->with('error', 'Produk Hukum ini bukan dokumen rahasia.');
        }

        if ($user->canViewRahasiaProdukHukum() || $this->canUserAccessProdukHukum($user, $produkHukum)) {
            return back()->with('success', 'Anda sudah memiliki akses ke Produk Hukum ini.');
        }

        $existingRequest = DeleteRequest::where('type_code', 'produk_hukum')
            ->where('table_name', 'tb_produk_hukum')
            ->where('record_key', (string) $produkHukum->id)
            ->where('requested_by', $user->id)
            ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
            ->first();

        if ($existingRequest) {
            return back()->with('error', 'Pengajuan lihat Produk Hukum ini masih menunggu proses approval.');
        }

        DeleteRequest::create([
            'type_code' => 'produk_hukum',
            'database_name' => 'sidewas_produk_hukum',
            'table_name' => 'tb_produk_hukum',
            'record_key' => (string) $produkHukum->id,
            'record_label' => $produkHukum->kode_produk_hukum . ' - ' . $produkHukum->judul,
            'reason' => json_encode([
                'action' => 'view_produk_hukum',
                'produk_hukum_id' => $produkHukum->id,
                'kode_produk_hukum' => $produkHukum->kode_produk_hukum,
                'judul' => $produkHukum->judul,
                'catatan' => $request->input('reason'),
            ]),
            'requested_by' => $user->id,
            'status' => 'pending_admin_verification',
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan lihat Produk Hukum rahasia berhasil dikirim.');
    }

    public function requestDelete(Request $request, ProdukHukum $produkHukum)
    {
        $user = User::find(Auth::id());

        if (! $user || ! $user->canDeleteProdukHukum()) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus Produk Hukum.');
        }

        if ($user->isSuperAdmin()) {
            $this->deleteProdukHukumData($produkHukum, $user, $request);

            return back()->with('success', 'Produk Hukum berhasil dihapus.');
        }

        $existingRequest = DeleteRequest::where('type_code', 'produk_hukum')
            ->where('table_name', 'tb_produk_hukum')
            ->where('record_key', (string) $produkHukum->id)
            ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
            ->where('reason', 'like', '%"action":"delete_produk_hukum"%')
            ->first();

        if ($existingRequest) {
            return back()->with('error', 'Pengajuan hapus Produk Hukum ini masih menunggu proses approval.');
        }

        DeleteRequest::create([
            'type_code' => 'produk_hukum',
            'database_name' => 'sidewas_produk_hukum',
            'table_name' => 'tb_produk_hukum',
            'record_key' => (string) $produkHukum->id,
            'record_label' => $produkHukum->kode_produk_hukum . ' - ' . $produkHukum->judul,
            'reason' => json_encode([
                'action' => 'delete_produk_hukum',
                'produk_hukum_id' => $produkHukum->id,
                'kode_produk_hukum' => $produkHukum->kode_produk_hukum,
                'judul' => $produkHukum->judul,
                'nomor_peraturan_keputusan' => $produkHukum->nomor_peraturan_keputusan,
                'tahun_peraturan' => $produkHukum->tahun_peraturan,
                'jenis_bentuk_peraturan' => $produkHukum->jenis_bentuk_peraturan,
                'sifat_dokumen' => $produkHukum->sifat_dokumen,
                'catatan' => $request->input('reason', 'Mengajukan hapus Produk Hukum.'),
            ]),
            'requested_by' => $user->id,
            'status' => 'pending_super_admin_approval',
            'requested_at' => now(),
        ]);

        return back()->with('success', 'Pengajuan hapus Produk Hukum berhasil dikirim ke Super Admin.');
    }

    public function downloadFile(ProdukHukumFile $file)
    {
        $user = User::find(Auth::id());
        $produkHukum = $file->produkHukum;

        if (! $user || ! $produkHukum || ! $this->canUserAccessProdukHukum($user, $produkHukum)) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh file ini.');
        }

        if ($file->bentuk_file === 'link') {
            if (! $file->link_file) {
                abort(404, 'Link tidak ditemukan.');
            }

            return redirect()->away($file->link_file);
        }

        if (! $file->path_file || ! Storage::disk('public')->exists($file->path_file)) {
            abort(404, 'File tidak ditemukan.');
        }

        $filePath = storage_path('app/public/' . $file->path_file);

        return response()->download($filePath, $file->nama_file);
    }

    private function canUserAccessProdukHukum(User $user, ProdukHukum $produkHukum): bool
    {
        if ($user->canViewRahasiaProdukHukum() || $produkHukum->sifat_dokumen === 'publik') {
            return true;
        }

        return in_array((int) $produkHukum->id, $this->approvedRahasiaProdukHukumIds($user), true);
    }

    private function approvedRahasiaProdukHukumIds(User $user): array
    {
        return DeleteRequest::where('type_code', 'produk_hukum')
            ->where('table_name', 'tb_produk_hukum')
            ->where('requested_by', $user->id)
            ->where('status', 'approved')
            ->get()
            ->filter(function (DeleteRequest $request) {
                $payload = json_decode($request->reason ?? '', true);

                return ($payload['action'] ?? null) === 'view_produk_hukum';
            })
            ->pluck('record_key')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    public function deleteProdukHukumData(ProdukHukum $produkHukum, User $user, Request $request, ?DeleteRequest $deleteRequest = null): void
    {
        DB::connection('mysql_produk_hukum')->transaction(function () use ($produkHukum, $user, $request, $deleteRequest) {
            $produkHukum->load(['files', 'relasis']);

            $oldValues = [
                'produk_hukum' => $produkHukum->toArray(),
                'delete_request' => $deleteRequest?->toArray(),
            ];

            $recordKey = (string) $produkHukum->id;

            foreach ($produkHukum->files as $file) {
                if ($file->bentuk_file !== 'link' && $file->path_file && Storage::disk('public')->exists($file->path_file)) {
                    Storage::disk('public')->delete($file->path_file);
                }
            }

            ProdukHukumRelasi::where('produk_hukum_terkait_id', $produkHukum->id)
                ->update([
                    'produk_hukum_terkait_id' => null,
                    'updated_by' => $user->id,
                ]);

            $produkHukum->files()->delete();
            $produkHukum->relasis()->delete();
            $produkHukum->delete();

            if ($deleteRequest) {
                $deleteRequest->update([
                    'status' => 'approved',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
            }

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'produk_hukum',
                'database_name' => 'sidewas_produk_hukum',
                'table_name' => 'tb_produk_hukum',
                'record_key' => $recordKey,
                'action' => $deleteRequest ? 'approve_delete_produk_hukum_request' : 'delete',
                'description' => $deleteRequest
                    ? 'Super Admin menyetujui pengajuan hapus Produk Hukum.'
                    : 'Super Admin menghapus Produk Hukum.',
                'old_values' => $oldValues,
                'new_values' => $deleteRequest ? ['request' => $deleteRequest->fresh()->toArray()] : null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });
    }
}
