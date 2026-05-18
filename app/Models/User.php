<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\RoleType;
use App\Models\SnpButir;

class User extends Authenticatable
{
    protected $connection = 'mysql';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'provider',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userRoleTypes()
    {
        return $this->hasMany(UserRoleType::class, 'user_id', 'id');
    }

    public function roleTypes()
    {
        return $this->belongsToMany(
            RoleType::class,
            'tb_user_role_type',
            'user_id',
            'role_type_id'
        )->withPivot('status')->withTimestamps();
    }

    public function unitKerja()
    {
        return $this->belongsToMany(
            UnitKerja::class,
            'tb_user_unit_kerja',
            'user_id',
            'unit_kerja_id'
        )->withPivot('status')->withTimestamps();
    }

    public function komite()
    {
        return $this->belongsToMany(
            Komite::class,
            'tb_user_komite',
            'user_id',
            'komite_id'
        )->withPivot('status')->withTimestamps();
    }

    public function logs()
    {
        return $this->hasMany(LogActivity::class, 'user_id', 'id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleTypes()
            ->wherePivot('status', 'active')
            ->whereHas('role', function ($query) {
                $query->where('name', 'super_admin')
                    ->where('is_universal', true);
            })
            ->exists();
    }

    public function canAccessType(string $typeCode): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roleTypes()
            ->wherePivot('status', 'active')
            ->whereHas('type', function ($query) use ($typeCode) {
                $query->where('code', $typeCode);
            })
            ->exists();
    }

    public static function isAllowedEmailDomain(string $email): bool
    {
        return str_ends_with(strtolower($email), '@bpjsketenagakerjaan.go.id');
    }

    public function hasRoleType(string $roleTypeName): bool
    {
        return $this->roleTypes()
            ->wherePivot('status', 'active')
            ->where('tb_role_type.name', $roleTypeName)
            ->exists();
    }

    public function canAccessSnpPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_snp')
            || $this->hasRoleType('moderator_snp')
            || $this->hasRoleType('pic_snp');
    }

    public function canCreateSnpPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_snp')
            || $this->hasRoleType('moderator_snp');
    }

    public function canRequestDeleteSnpPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_snp')
            || $this->hasRoleType('moderator_snp');
    }

    public function isSnpAdmin(): bool
    {
        return $this->hasRoleType('admin_snp');
    }

    public function isSnpModerator(): bool
    {
        return $this->hasRoleType('moderator_snp');
    }

    public function hasAnyRoleType(array $roleTypeNames): bool
    {
        return $this->roleTypes()
            ->wherePivot('status', 'active')
            ->whereIn('tb_role_type.name', $roleTypeNames)
            ->exists();
    }

    public function canAccessPengajuan(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAnyRoleType([
                'admin_snp',
                'admin_ragab',
                'admin_rawas',
                'admin_djsn',
            ]);
    }

    public function pengajuanTypeCodes(): array
    {
        if ($this->isSuperAdmin()) {
            return ['snp', 'ragab', 'rawas', 'djsn'];
        }

        $roleTypes = $this->roleTypes()
            ->wherePivot('status', 'active')
            ->pluck('tb_role_type.name')
            ->toArray();

        $types = [];

        foreach ($roleTypes as $roleTypeName) {
            if ($roleTypeName === 'admin_snp') {
                $types[] = 'snp';
            }

            if ($roleTypeName === 'admin_ragab') {
                $types[] = 'ragab';
            }

            if ($roleTypeName === 'admin_rawas') {
                $types[] = 'rawas';
            }

            if ($roleTypeName === 'admin_djsn') {
                $types[] = 'djsn';
            }
        }

        return array_values(array_unique($types));
    }

    public function canVerifyPengajuanType(string $typeCode): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return $this->hasRoleType('admin_' . $typeCode);
    }

    public function canApprovePengajuan(): bool
    {
        return $this->isSuperAdmin();
    }

    public function unitKerjaIds(): array
    {
        return $this->unitKerja()
            ->wherePivot('status', 'active')
            ->pluck('tb_unit_kerja.id')
            ->toArray();
    }

    public function canCreateSnpTanggapanForButir(SnpButir $butir): bool
    {
        $hasAllowedRole = $this->hasRoleType('pic_snp')
            || $this->hasRoleType('moderator_snp');

        if (!$hasAllowedRole) {
            return false;
        }

        $userUnitKerjaIds = $this->unitKerjaIds();

        if (empty($userUnitKerjaIds)) {
            return false;
        }

        $picUnitKerjaIds = $butir->butirPics()
            ->whereIn('jenis_pic', ['utama', 'pendukung'])
            ->whereNotNull('unit_kerja_id')
            ->pluck('unit_kerja_id')
            ->toArray();

        return count(array_intersect($userUnitKerjaIds, $picUnitKerjaIds)) > 0;
    }

    public function canAccessSnpTanggapan(): bool
    {
        return $this->hasRoleType('pic_snp')
            || $this->hasRoleType('moderator_snp')
            || $this->hasRoleType('admin_snp')
            || $this->isSuperAdmin();
    }

    public function komiteIds(): array
    {
        return $this->komite()
            ->wherePivot('status', 'active')
            ->pluck('tb_komite.id')
            ->toArray();
    }

    public function canAccessSnpReview(): bool
    {
        return ($this->hasRoleType('pic_snp') || $this->hasRoleType('moderator_snp'))
            && count($this->komiteIds()) > 0;
    }

    public function canReviewSnpByKomite(?int $komiteId): bool
    {
        if (!$this->hasRoleType('pic_snp') && !$this->hasRoleType('moderator_snp')) {
            return false;
        }

        if (!$komiteId) {
            return false;
        }

        return in_array($komiteId, $this->komiteIds());
    }

    public function canCreateSnpTindakLanjutForButir(SnpButir $butir): bool
    {
        $hasAllowedRole = $this->hasRoleType('pic_snp')
            || $this->hasRoleType('moderator_snp');

        if (!$hasAllowedRole) {
            return false;
        }

        $userUnitKerjaIds = $this->unitKerjaIds();

        if (empty($userUnitKerjaIds)) {
            return false;
        }

        $picUnitKerjaIds = $butir->butirPics()
            ->whereIn('jenis_pic', ['utama', 'pendukung'])
            ->whereNotNull('unit_kerja_id')
            ->pluck('unit_kerja_id')
            ->toArray();

        return count(array_intersect($userUnitKerjaIds, $picUnitKerjaIds)) > 0;
    }

    public function canAccessSnpTindakLanjut(): bool
    {
        return $this->hasRoleType('pic_snp')
            || $this->hasRoleType('moderator_snp')
            || $this->hasRoleType('admin_snp')
            || $this->isSuperAdmin();
    }
}
