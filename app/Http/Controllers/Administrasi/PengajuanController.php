<?php

namespace App\Http\Controllers\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\DjsnRecord;
use App\Models\EksternalRecord;
use App\Models\LogActivity;
use App\Models\ProdukHukum;
use App\Models\RagabRecord;
use App\Models\RawasRecord;
use App\Models\SnpRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PengajuanController extends Controller
{
    public function index()
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canAccessPengajuan()) {
            abort(403, 'Anda tidak memiliki akses ke halaman pengajuan.');
        }

        $query = DeleteRequest::with([
            'requester',
            'verifier',
            'approver',
            'rejecter',
        ]);

        if (! $user->isSuperAdmin()) {
            $query->whereIn('type_code', $user->pengajuanTypeCodes());
        }

        $pengajuan = $query->latest('updated_at')->paginate(10);

        return view('layouts.administrasi.pengajuan', compact('pengajuan'));
    }

    public function verify(Request $request, DeleteRequest $deleteRequest)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canVerifyPengajuanType($deleteRequest->type_code)) {
            abort(403, 'Anda tidak memiliki akses untuk memverifikasi pengajuan ini.');
        }

        if ($deleteRequest->status !== 'pending_admin_verification') {
            return back()->with('error', 'Pengajuan ini tidak bisa diverifikasi.');
        }

        $oldValues = $deleteRequest->toArray();

        $deleteRequest->update([
            'status' => 'pending_super_admin_approval',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => $deleteRequest->type_code,
            'database_name' => 'sidewas',
            'table_name' => 'tb_delete_requests',
            'record_key' => $deleteRequest->record_key,
            'action' => 'verify_request',
            'description' => 'Admin memverifikasi pengajuan.',
            'old_values' => $oldValues,
            'new_values' => $deleteRequest->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Pengajuan berhasil diverifikasi dan diteruskan ke Super Admin.');
    }

    public function approve(DeleteRequest $deleteRequest)
    {
        $user = User::find(Auth::id());

        if (!$user || !$user->canApprovePengajuan()) {
            abort(403, 'Hanya Super Admin yang dapat menyetujui pengajuan.');
        }

        if ($deleteRequest->status !== 'pending_super_admin_approval') {
            return back()->with('error', 'Pengajuan ini belum siap disetujui.');
        }

        if ($deleteRequest->table_name === 'users') {
            $this->approveUserRequest($deleteRequest, $user);

            return back()->with('success', 'Pengajuan disetujui dan data user berhasil diproses.');
        }

        if ($deleteRequest->type_code === 'produk_hukum' && $deleteRequest->table_name === 'tb_produk_hukum') {
            $this->approveProdukHukumRequest($deleteRequest, $user);

            return back()->with('success', 'Pengajuan disetujui dan akses Produk Hukum berhasil diberikan.');
        }

        if ($deleteRequest->table_name !== 'tb_record') {
            return back()->with('error', 'Approval untuk tipe ini belum tersedia.');
        }

        if (! $this->approveRecordDeleteRequest($deleteRequest, $user)) {
            return back()->with('error', 'Approval untuk tipe ini belum tersedia.');
        }

        return back()->with('success', 'Pengajuan disetujui dan data berhasil dihapus.');
    }

    private function approveRecordDeleteRequest(DeleteRequest $deleteRequest, User $user): bool
    {
        $configs = [
            'snp' => [
                'connection' => 'mysql_snp',
                'database' => 'sidewas_snp',
                'model' => SnpRecord::class,
                'key' => 'id_snp',
                'label' => 'SNP',
                'relations' => ['butirSnp.butirPics', 'cluster', 'subCluster'],
                'file_fields' => ['dokumen', 'dokumen_memo'],
            ],
            'ragab' => [
                'connection' => 'mysql_ragab',
                'database' => 'sidewas_ragab',
                'model' => RagabRecord::class,
                'key' => 'id_ragab',
                'label' => 'RAGAB',
                'relations' => ['butirRagab.butirPics', 'butirRagab.cluster', 'butirRagab.subCluster'],
                'file_fields' => ['dokumen', 'dokumen_memo'],
            ],
            'rawas' => [
                'connection' => 'mysql_rawas',
                'database' => 'sidewas_rawas',
                'model' => RawasRecord::class,
                'key' => 'id_rawas',
                'label' => 'RAWAS',
                'relations' => ['butirRawas.butirPics', 'cluster', 'subCluster'],
                'file_fields' => ['dokumen_memo'],
            ],
            'djsn' => [
                'connection' => 'mysql_djsn',
                'database' => 'sidewas_djsn',
                'model' => DjsnRecord::class,
                'key' => 'id_djsn',
                'label' => 'DJSN',
                'relations' => ['butirDjsn.butirPics', 'butirDjsn.cluster', 'butirDjsn.subCluster'],
                'file_fields' => ['dokumen'],
            ],
            'eksternal' => [
                'connection' => 'mysql_eksternal',
                'database' => 'sidewas_eksternal',
                'model' => EksternalRecord::class,
                'key' => 'id_eksternal',
                'label' => 'Eksternal',
                'relations' => ['butirEksternal.butirPics', 'butirEksternal.cluster', 'butirEksternal.subCluster'],
                'file_fields' => ['dokumen', 'dokumen_memo'],
            ],
        ];

        $config = $configs[$deleteRequest->type_code] ?? null;

        if (! $config) {
            return false;
        }

        DB::connection($config['connection'])->transaction(function () use ($config, $deleteRequest, $user) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            $modelClass = $config['model'];
            $record = $modelClass::where($config['key'], $deleteRequest->record_key)->firstOrFail();

            $oldRecord = $record->load($config['relations'])->toArray();
            $oldRequest = $deleteRequest->toArray();
            $recordKey = $record->{$config['key']};

            foreach ($config['file_fields'] as $fileField) {
                if ($record->{$fileField} && Storage::disk('public')->exists($record->{$fileField})) {
                    Storage::disk('public')->delete($record->{$fileField});
                }
            }

            $record->delete();

            $deleteRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => $deleteRequest->type_code,
                'database_name' => $config['database'],
                'table_name' => 'tb_record',
                'record_key' => $recordKey,
                'action' => 'approve_delete_request',
                'description' => 'Super Admin menyetujui pengajuan hapus dan menghapus perekaman ' . $config['label'] . '.',
                'old_values' => [
                    'delete_request' => $oldRequest,
                    'record' => $oldRecord,
                ],
                'new_values' => $deleteRequest->fresh()->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return true;
    }

    private function approveUserRequest(DeleteRequest $deleteRequest, User $user): void
    {
        $requestPayload = json_decode($deleteRequest->reason ?? '', true);
        $action = $requestPayload['action'] ?? null;

        if ($action === 'update_user') {
            $this->approveUserUpdateRequest($deleteRequest, $user, $requestPayload);

            return;
        }

        if ($action === 'delete_user') {
            $this->approveUserDeleteRequest($deleteRequest, $user, $requestPayload);

            return;
        }

        throw ValidationException::withMessages([
            'pengajuan' => 'Jenis pengajuan user tidak valid.',
        ]);
    }

    private function approveUserUpdateRequest(DeleteRequest $deleteRequest, User $user, array $requestPayload): void
    {
        $payload = $requestPayload['payload'] ?? null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'pengajuan' => 'Payload pengajuan edit user tidak valid.',
            ]);
        }

        DB::transaction(function () use ($deleteRequest, $user, $payload) {
            $targetUser = User::with(['roleTypes', 'unitKerja', 'komite'])
                ->findOrFail((int) $deleteRequest->record_key);

            $oldValues = [
                'user' => $targetUser->toArray(),
                'request' => $deleteRequest->toArray(),
            ];

            $targetUser->update([
                'name' => $payload['name'] ?? $targetUser->name,
                'email' => $payload['email'] ?? $targetUser->email,
            ]);

            $targetUser->roleTypes()->sync(
                collect($payload['role_type_ids'] ?? [])
                    ->mapWithKeys(fn ($id) => [(int) $id => ['status' => 'active']])
                    ->all()
            );

            $targetUser->unitKerja()->sync([]);
            $targetUser->komite()->sync([]);

            $assignment = $payload['assignment'] ?? ['type' => null, 'id' => null];

            if (($assignment['type'] ?? null) === 'unit') {
                $targetUser->unitKerja()->attach((int) $assignment['id'], ['status' => 'active']);
            }

            if (($assignment['type'] ?? null) === 'komite') {
                $targetUser->komite()->attach((int) $assignment['id'], ['status' => 'active']);
            }

            $deleteRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => $deleteRequest->type_code,
                'database_name' => 'sidewas',
                'table_name' => 'users',
                'record_key' => (string) $targetUser->id,
                'action' => 'approve_update_user_request',
                'description' => 'Super Admin menyetujui pengajuan edit user.',
                'old_values' => $oldValues,
                'new_values' => [
                    'user' => $targetUser->fresh(['roleTypes', 'unitKerja', 'komite'])->toArray(),
                    'request' => $deleteRequest->fresh()->toArray(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    private function approveUserDeleteRequest(DeleteRequest $deleteRequest, User $user, array $requestPayload): void
    {
        $payload = $requestPayload['payload'] ?? null;

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'pengajuan' => 'Payload pengajuan hapus user tidak valid.',
            ]);
        }

        if ((int) $user->id === (int) $deleteRequest->record_key) {
            throw ValidationException::withMessages([
                'pengajuan' => 'Anda tidak dapat menyetujui penghapusan akun yang sedang digunakan.',
            ]);
        }

        DB::transaction(function () use ($deleteRequest, $user) {
            $targetUser = User::with(['roleTypes', 'unitKerja', 'komite'])
                ->findOrFail((int) $deleteRequest->record_key);

            $oldValues = [
                'user' => $targetUser->toArray(),
                'request' => $deleteRequest->toArray(),
            ];

            $recordKey = (string) $targetUser->id;

            $targetUser->roleTypes()->sync([]);
            $targetUser->unitKerja()->sync([]);
            $targetUser->komite()->sync([]);
            $targetUser->delete();

            $deleteRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => $deleteRequest->type_code,
                'database_name' => 'sidewas',
                'table_name' => 'users',
                'record_key' => $recordKey,
                'action' => 'approve_delete_user_request',
                'description' => 'Super Admin menyetujui pengajuan hapus user.',
                'old_values' => $oldValues,
                'new_values' => [
                    'request' => $deleteRequest->fresh()->toArray(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    private function approveProdukHukumRequest(DeleteRequest $deleteRequest, User $user): void
    {
        $requestPayload = json_decode($deleteRequest->reason ?? '', true);

        if (($requestPayload['action'] ?? null) !== 'view_produk_hukum') {
            throw ValidationException::withMessages([
                'pengajuan' => 'Jenis pengajuan Produk Hukum tidak valid.',
            ]);
        }

        DB::transaction(function () use ($deleteRequest, $requestPayload, $user) {
            $produkHukum = ProdukHukum::where('id', (int) $deleteRequest->record_key)->firstOrFail();

            $oldValues = [
                'request' => $deleteRequest->toArray(),
                'produk_hukum' => $produkHukum->toArray(),
            ];

            $deleteRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'produk_hukum',
                'database_name' => 'sidewas_produk_hukum',
                'table_name' => 'tb_produk_hukum',
                'record_key' => (string) $produkHukum->id,
                'action' => 'approve_view_produk_hukum_request',
                'description' => 'Super Admin menyetujui pengajuan lihat Produk Hukum rahasia.',
                'old_values' => $oldValues,
                'new_values' => [
                    'request' => $deleteRequest->fresh()->toArray(),
                    'payload' => $requestPayload,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    public function reject(Request $request, DeleteRequest $deleteRequest)
    {
        $user = User::find(Auth::id());

        $canReject = $user
            && (
                $user->canApprovePengajuan()
                || $user->canVerifyPengajuanType($deleteRequest->type_code)
            );

        if (!$canReject) {
            abort(403, 'Anda tidak memiliki akses untuk menolak pengajuan.');
        }

        if (
            !in_array($deleteRequest->status, [
                'pending_admin_verification',
                'pending_super_admin_approval',
            ])
        ) {
            return back()->with('error', 'Pengajuan ini tidak bisa ditolak.');
        }

        $oldValues = $deleteRequest->toArray();

        $deleteRequest->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'reason' => trim(($deleteRequest->reason ?? '') . "\n\nAlasan penolakan: " . $request->input('reject_reason')),
        ]);

        LogActivity::create([
            'user_id' => $user->id,
            'type_code' => $deleteRequest->type_code,
            'database_name' => 'sidewas',
            'table_name' => 'tb_delete_requests',
            'record_key' => $deleteRequest->record_key,
            'action' => 'reject_delete_request',
            'description' => 'Pengajuan hapus ditolak.',
            'old_values' => $oldValues,
            'new_values' => $deleteRequest->fresh()->toArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', 'Pengajuan berhasil ditolak.');
    }
}
