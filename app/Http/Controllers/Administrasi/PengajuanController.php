<?php

namespace App\Http\Controllers\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\LogActivity;
use App\Models\SnpRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


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
        ])
            ->where('table_name', 'tb_record');

        if ($user->isSuperAdmin()) {
            $query->where('status', 'pending_super_admin_approval');
        } else {
            $query->whereIn('type_code', $user->pengajuanTypeCodes())
                ->where('status', 'pending_admin_verification');
        }

        $pengajuan = $query->latest()->paginate(10);

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
            'action' => 'verify_delete_request',
            'description' => 'Admin memverifikasi pengajuan hapus.',
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

        if ($deleteRequest->type_code !== 'snp') {
            return back()->with('error', 'Approval untuk tipe ini belum tersedia.');
        }

        DB::connection('mysql_snp')->transaction(function () use ($deleteRequest, $user) {
            $record = SnpRecord::where('id_snp', $deleteRequest->record_key)->firstOrFail();

            $oldRecord = $record->load([
                'butirSnp.butirPics',
                'cluster',
                'subCluster',
            ])->toArray();

            $oldRequest = $deleteRequest->toArray();

            $recordKey = $record->id_snp;

            if ($record->dokumen && Storage::disk('public')->exists($record->dokumen)) {
                Storage::disk('public')->delete($record->dokumen);
            }

            $record->delete();

            $deleteRequest->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            LogActivity::create([
                'user_id' => $user->id,
                'type_code' => 'snp',
                'database_name' => 'sidewas_snp',
                'table_name' => 'tb_record',
                'record_key' => $recordKey,
                'action' => 'approve_delete_request',
                'description' => 'Super Admin menyetujui pengajuan hapus dan menghapus perekaman SNP.',
                'old_values' => [
                    'delete_request' => $oldRequest,
                    'record' => $oldRecord,
                ],
                'new_values' => $deleteRequest->fresh()->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return back()->with('success', 'Pengajuan disetujui dan data berhasil dihapus.');
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
