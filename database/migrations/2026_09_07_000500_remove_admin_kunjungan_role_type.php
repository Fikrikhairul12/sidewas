<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $roleTypeId = $connection->table('tb_role_type')
            ->where('name', 'admin_kunjungan')
            ->value('id');

        if (! $roleTypeId) {
            return;
        }

        $connection->table('tb_user_role_type')->where('role_type_id', $roleTypeId)->delete();
        $connection->table('tb_role_type')->where('id', $roleTypeId)->delete();
    }

    public function down(): void
    {
        $connection = DB::connection();
        $adminRoleId = $connection->table('tb_role')->where('name', 'admin')->value('id');
        $kunjunganTypeId = $connection->table('tb_type')->where('code', 'kunjungan')->value('id');

        if (! $adminRoleId || ! $kunjunganTypeId) {
            return;
        }

        $connection->table('tb_role_type')->updateOrInsert(
            ['role_id' => $adminRoleId, 'type_id' => $kunjunganTypeId],
            [
                'name' => 'admin_kunjungan',
                'keterangan' => 'Admin Kunjungan Kerja',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
