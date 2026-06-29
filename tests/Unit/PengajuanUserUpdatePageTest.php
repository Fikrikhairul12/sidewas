<?php

test('pengajuan page supports user update approval flow', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath.'/app/Http/Controllers/Administrasi/PengajuanController.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/administrasi/pengajuan.blade.php');

    expect($controller)
        ->toContain('$deleteRequest->table_name === \'users\'')
        ->toContain('approveUserRequest')
        ->toContain('approveUserUpdateRequest')
        ->toContain('approveUserDeleteRequest')
        ->toContain('approveProdukHukumRequest')
        ->toContain('json_decode($deleteRequest->reason')
        ->toContain('$targetUser->update')
        ->toContain('$targetUser->roleTypes()->sync')
        ->toContain('$targetUser->unitKerja()->sync([])')
        ->toContain('$targetUser->komite()->sync([])')
        ->toContain("'action' => 'approve_update_user_request'")
        ->toContain("'action' => 'approve_delete_user_request'");

    expect($view)
        ->toContain('$isUserUpdate = $isUserRequest && $userAction === \'update_user\'')
        ->toContain('$isUserDelete = $isUserRequest && $userAction === \'delete_user\'')
        ->toContain('$isProdukHukumView = $item->type_code === \'produk_hukum\'')
        ->toContain('Edit User')
        ->toContain('Hapus User')
        ->toContain('Lihat Produk Hukum')
        ->toContain('Pengajuan edit/hapus user, hapus perekaman, dan akses dokumen rahasia')
        ->toContain('Setujui perubahan user ini?')
        ->toContain('Setujui penghapusan user ini?')
        ->toContain('Setujui akses lihat Produk Hukum ini?');
});
