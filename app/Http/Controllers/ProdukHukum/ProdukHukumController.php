<?php

namespace App\Http\Controllers\ProdukHukum;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\LogActivity;
use App\Models\ProdukHukum;
use App\Models\ProdukHukumFile;
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
                    ->orWhere('tipe_dokumen', 'like', "%{$keyword}%")
                    ->orWhere('nomor_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('tahun_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('jenis_bentuk_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('singkatan_peraturan', 'like', "%{$keyword}%")
                    ->orWhere('subjek', 'like', "%{$keyword}%")
                    ->orWhere('bidang_hukum', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('sifat_dokumen')) {
            $query->where('sifat_dokumen', $request->sifat_dokumen);
        }

        if ($request->filled('status_publish')) {
            $query->where('status_publish', $request->status_publish);
        }

        $produkHukums = $query
            ->paginate(8)
            ->withQueryString();

        $pendingAccessIds = DeleteRequest::where('type_code', 'produk_hukum')
            ->where('table_name', 'tb_produk_hukum')
            ->where('requested_by', $user->id)
            ->whereIn('status', ['pending_admin_verification', 'pending_super_admin_approval'])
            ->pluck('record_key')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $approvedAccessIds = $this->approvedRahasiaProdukHukumIds($user);

        return view('layouts.produk-hukum.index', compact(
            'produkHukums',
            'pendingAccessIds',
            'approvedAccessIds'
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
            'tipe_dokumen' => ['nullable', 'string', 'max:255'],
            'judul' => ['required', 'string'],
            'nomor_peraturan' => ['nullable', 'string', 'max:255'],
            'tahun_peraturan' => ['nullable', 'integer', 'min:1900', 'max:' . (now()->year + 1)],
            'jenis_bentuk_peraturan' => ['nullable', 'string', 'max:255'],
            'singkatan_peraturan' => ['nullable', 'string', 'max:50'],
            'tempat_penetapan' => ['nullable', 'string', 'max:255'],
            'tanggal_penetapan' => ['nullable', 'date'],
            'tanggal_diundangkan' => ['nullable', 'date'],
            'sumber_ln' => ['nullable', 'string', 'max:255'],
            'sumber_tln' => ['nullable', 'string', 'max:255'],
            'subjek' => ['nullable', 'string'],
            'bahasa' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'bidang_hukum' => ['nullable', 'string', 'max:255'],
            'abstrak' => ['nullable', 'string'],
            'status_peraturan' => ['nullable', 'string', 'max:255'],
            'sifat_dokumen' => ['required', Rule::in(['publik', 'rahasia'])],
            'status_publish' => ['required', Rule::in(['draft', 'berlaku', 'tidak_berlaku'])],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
            'jenis_file' => ['nullable', 'string', 'max:255'],
            'jenis_relasi' => ['nullable', Rule::in(['mencabut', 'dicabut_oleh', 'mengubah', 'diubah_oleh', 'terkait'])],
            'produk_hukum_terkait_id' => ['nullable', 'integer', 'exists:mysql_produk_hukum.tb_produk_hukum,id'],
            'nomor_peraturan_terkait' => ['nullable', 'string', 'max:255'],
            'judul_terkait' => ['nullable', 'string'],
            'keterangan_relasi' => ['nullable', 'string'],
        ]);

        DB::connection('mysql_produk_hukum')->transaction(function () use ($request, $validated, $user) {
            $produkHukum = ProdukHukum::create([
                'kode_produk_hukum' => $validated['kode_produk_hukum'] ?? null,
                'tipe_dokumen' => $validated['tipe_dokumen'] ?? null,
                'judul' => $validated['judul'],
                'nomor_peraturan' => $validated['nomor_peraturan'] ?? null,
                'tahun_peraturan' => $validated['tahun_peraturan'] ?? null,
                'jenis_bentuk_peraturan' => $validated['jenis_bentuk_peraturan'] ?? null,
                'singkatan_peraturan' => $validated['singkatan_peraturan'] ?? null,
                'tempat_penetapan' => $validated['tempat_penetapan'] ?? null,
                'tanggal_penetapan' => $validated['tanggal_penetapan'] ?? null,
                'tanggal_diundangkan' => $validated['tanggal_diundangkan'] ?? null,
                'sumber_ln' => $validated['sumber_ln'] ?? null,
                'sumber_tln' => $validated['sumber_tln'] ?? null,
                'subjek' => $validated['subjek'] ?? null,
                'bahasa' => $validated['bahasa'] ?? 'Indonesia',
                'lokasi' => $validated['lokasi'] ?? null,
                'bidang_hukum' => $validated['bidang_hukum'] ?? null,
                'abstrak' => $validated['abstrak'] ?? null,
                'status_peraturan' => $validated['status_peraturan'] ?? null,
                'sifat_dokumen' => $validated['sifat_dokumen'],
                'status_publish' => $validated['status_publish'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($request->file('files', []) as $file) {
                $path = $file->store('produk-hukum', 'public');

                ProdukHukumFile::create([
                    'produk_hukum_id' => $produkHukum->id,
                    'nama_file' => $file->getClientOriginalName(),
                    'path_file' => $path,
                    'mime_type' => $file->getMimeType(),
                    'ukuran_file' => $file->getSize(),
                    'jenis_file' => $validated['jenis_file'] ?? 'lampiran',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            if (! empty($validated['jenis_relasi'])) {
                ProdukHukumRelasi::create([
                    'produk_hukum_id' => $produkHukum->id,
                    'jenis_relasi' => $validated['jenis_relasi'],
                    'produk_hukum_terkait_id' => $validated['produk_hukum_terkait_id'] ?? null,
                    'nomor_peraturan_terkait' => $validated['nomor_peraturan_terkait'] ?? null,
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

    public function downloadFile(ProdukHukumFile $file)
    {
        $user = User::find(Auth::id());
        $produkHukum = $file->produkHukum;

        if (! $user || ! $produkHukum || ! $this->canUserAccessProdukHukum($user, $produkHukum)) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh file ini.');
        }

        if (! Storage::disk('public')->exists($file->path_file)) {
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
}
