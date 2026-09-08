<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $now = now();

        $connection->table('tb_type')->updateOrInsert(
            ['code' => 'kunjungan'],
            [
                'name' => 'Kunjungan Kerja',
                'database_connection' => 'mysql_kunjungan',
                'database_name' => 'sidewas_kunjungan',
                'keterangan' => 'Tipe akses untuk modul monitoring kunjungan kerja.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $typeId = $connection->table('tb_type')->where('code', 'kunjungan')->value('id');
        $roles = $connection->table('tb_role')
            ->whereIn('name', ['moderator', 'pic', 'viewer'])
            ->pluck('id', 'name');

        foreach ([
            'moderator' => 'Moderator Kunjungan Kerja',
            'pic' => 'PIC Kunjungan Kerja',
            'viewer' => 'Pegawai (Viewer) Kunjungan Kerja',
        ] as $roleName => $description) {
            if (! $roles->has($roleName)) {
                continue;
            }

            $connection->table('tb_role_type')->updateOrInsert(
                ['role_id' => $roles[$roleName], 'type_id' => $typeId],
                [
                    'name' => $roleName.'_kunjungan',
                    'keterangan' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $deprecatedRoleTypeId = $connection->table('tb_role_type')
            ->where('name', 'admin_kunjungan')
            ->value('id');

        if ($deprecatedRoleTypeId) {
            $connection->table('tb_user_role_type')->where('role_type_id', $deprecatedRoleTypeId)->delete();
            $connection->table('tb_role_type')->where('id', $deprecatedRoleTypeId)->delete();
        }
    }

    public function down(): void
    {
        $connection = DB::connection();
        $roleTypeIds = $connection->table('tb_role_type')
            ->whereIn('name', ['moderator_kunjungan', 'pic_kunjungan', 'viewer_kunjungan'])
            ->pluck('id');

        $connection->table('tb_user_role_type')->whereIn('role_type_id', $roleTypeIds)->delete();
        $connection->table('tb_role_type')->whereIn('id', $roleTypeIds)->delete();
        $connection->table('tb_type')->where('code', 'kunjungan')->delete();
    }
};
