<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            ['id' => 1, 'name' => 'super_admin', 'display_name' => 'Super Admin', 'level' => 100, 'is_universal' => true, 'keterangan' => 'Role tertinggi yang dapat mengakses seluruh fitur dan seluruh database.'],
            ['id' => 2, 'name' => 'admin', 'display_name' => 'Admin', 'level' => 80, 'is_universal' => false, 'keterangan' => 'Role admin berdasarkan tipe akses.'],
            ['id' => 3, 'name' => 'moderator', 'display_name' => 'Moderator', 'level' => 60, 'is_universal' => false, 'keterangan' => 'Role moderator berdasarkan tipe akses.'],
            ['id' => 4, 'name' => 'pic', 'display_name' => 'PIC', 'level' => 40, 'is_universal' => false, 'keterangan' => 'Role PIC berdasarkan tipe akses.'],
            ['id' => 5, 'name' => 'viewer', 'display_name' => 'Viewer', 'level' => 20, 'is_universal' => false, 'keterangan' => 'Role pembaca/viewer berdasarkan tipe akses.'],
        ];

        foreach ($roles as $role) {
            DB::table('tb_role')->updateOrInsert(
                ['id' => $role['id']],
                [...$role, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $types = [
            ['id' => 1, 'code' => 'snp', 'name' => 'SNP', 'database_connection' => 'mysql_snp', 'database_name' => 'sidewas_snp', 'keterangan' => 'Tipe akses untuk SNP Dewas.'],
            ['id' => 2, 'code' => 'ragab', 'name' => 'RAGAB', 'database_connection' => 'mysql_ragab', 'database_name' => 'sidewas_ragab', 'keterangan' => 'Tipe akses untuk RAGAB.'],
            ['id' => 3, 'code' => 'rawas', 'name' => 'RAWAS', 'database_connection' => 'mysql_rawas', 'database_name' => 'sidewas_rawas', 'keterangan' => 'Tipe akses untuk RAWAS.'],
            ['id' => 4, 'code' => 'djsn', 'name' => 'DJSN', 'database_connection' => 'mysql_djsn', 'database_name' => 'sidewas_djsn', 'keterangan' => 'Tipe akses untuk DJSN.'],
        ];

        foreach ($types as $type) {
            DB::table('tb_type')->updateOrInsert(
                ['id' => $type['id']],
                [...$type, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        DB::table('tb_role_type')->updateOrInsert(
            ['role_id' => 1, 'type_id' => null],
            [
                'name' => 'super_admin',
                'keterangan' => 'Akses universal seluruh tipe.',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $roleNames = [2 => 'admin', 3 => 'moderator', 4 => 'pic', 5 => 'viewer'];
        $typeCodes = [1 => 'snp', 2 => 'ragab', 3 => 'rawas', 4 => 'djsn'];

        foreach ($roleNames as $roleId => $roleName) {
            foreach ($typeCodes as $typeId => $typeCode) {
                DB::table('tb_role_type')->updateOrInsert(
                    ['role_id' => $roleId, 'type_id' => $typeId],
                    [
                        'name' => $roleName . '_' . $typeCode,
                        'keterangan' => ucfirst($roleName) . ' ' . strtoupper($typeCode),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
