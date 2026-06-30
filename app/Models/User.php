<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use App\Models\LogActivity;
use App\Models\RagabButir;
use App\Models\RagabReview;
use App\Models\RoleType;
use App\Models\SnpButir;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    public function latestLog()
    {
        return $this->hasOne(LogActivity::class, 'user_id', 'id')->latestOfMany();
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
            || $this->hasRoleType('pic_snp')
            || $this->hasRoleType('viewer_snp');
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
                'admin_produk_hukum',
                'admin_eksternal',
            ]);
    }

    public function pengajuanTypeCodes(): array
    {
        if ($this->isSuperAdmin()) {
            return ['snp', 'ragab', 'rawas', 'djsn', 'produk_hukum', 'eksternal'];
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

            if ($roleTypeName === 'admin_produk_hukum') {
                $types[] = 'produk_hukum';
            }

            if ($roleTypeName === 'admin_eksternal') {
                $types[] = 'eksternal';
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

    public function canAccessManajemenUser(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasAnyRoleType([
                'admin_snp',
                'admin_ragab',
                'admin_rawas',
                'admin_djsn',
                'admin_produk_hukum',
                'admin_eksternal',
                'moderator_snp',
                'moderator_ragab',
                'moderator_rawas',
                'moderator_djsn',
                'moderator_eksternal',
            ]);
    }

    public function canAccessProdukHukum(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_produk_hukum')
            || $this->hasRoleType('viewer_produk_hukum');
    }

    public function canCreateProdukHukum(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_produk_hukum');
    }

    public function canDeleteProdukHukum(): bool
    {
        return $this->canCreateProdukHukum();
    }

    public function canViewRahasiaProdukHukum(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_produk_hukum');
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
        if ($this->isSuperAdmin()) {
            return true;
        }

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
            ->where('jenis_pic', 'unit')
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
            || $this->hasRoleType('viewer_snp')
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
        return $this->hasRoleType('pic_snp')
            || $this->hasRoleType('moderator_snp')
            || $this->hasRoleType('admin_snp')
            || $this->hasRoleType('viewer_snp')
            || $this->isSuperAdmin();
    }

    public function canAccessSnpReport(): bool
    {
        return $this->hasRoleType('pic_snp')
            || $this->hasRoleType('moderator_snp')
            || $this->hasRoleType('admin_snp')
            || $this->hasRoleType('viewer_snp')
            || $this->isSuperAdmin();
    }

    public function canReviewSnpByKomite(?int $komiteId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

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
        if ($this->isSuperAdmin()) {
            return true;
        }

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
            || $this->hasRoleType('viewer_snp')
            || $this->isSuperAdmin();
    }

    public function canAccessRagabPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab')
            || $this->hasRoleType('viewer_ragab')
            || $this->hasRoleType('pic_ragab');
    }

    public function canCreateRagabPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab');
    }

    public function canRequestDeleteRagabPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab');
    }

    public function isRagabAdmin(): bool
    {
        return $this->hasRoleType('admin_ragab');
    }

    public function isRagabModerator(): bool
    {
        return $this->hasRoleType('moderator_ragab');
    }

    public function canAccessRagabTindakLanjut(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab')
            || $this->hasRoleType('viewer_ragab')
            || $this->hasRoleType('pic_ragab');
    }

    public function canAccessEksternalPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_eksternal')
            || $this->hasRoleType('moderator_eksternal')
            || $this->hasRoleType('viewer_eksternal')
            || $this->hasRoleType('pic_eksternal');
    }

    public function canCreateEksternalPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_eksternal')
            || $this->hasRoleType('moderator_eksternal');
    }

    public function canRequestDeleteEksternalPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_eksternal')
            || $this->hasRoleType('moderator_eksternal');
    }

    public function isEksternalAdmin(): bool
    {
        return $this->hasRoleType('admin_eksternal');
    }

    public function isEksternalModerator(): bool
    {
        return $this->hasRoleType('moderator_eksternal');
    }

    public function canAccessEksternalTindakLanjut(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_eksternal')
            || $this->hasRoleType('moderator_eksternal')
            || $this->hasRoleType('viewer_eksternal')
            || $this->hasRoleType('pic_eksternal');
    }

    public function canAccessRawasPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_rawas')
            || $this->hasRoleType('moderator_rawas')
            || $this->hasRoleType('viewer_rawas')
            || $this->hasRoleType('pic_rawas');
    }

    public function canCreateRawasPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_rawas')
            || $this->hasRoleType('moderator_rawas');
    }

    public function canRequestDeleteRawasPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_rawas')
            || $this->hasRoleType('moderator_rawas');
    }

    public function isRawasAdmin(): bool
    {
        return $this->hasRoleType('admin_rawas');
    }

    public function isRawasModerator(): bool
    {
        return $this->hasRoleType('moderator_rawas');
    }

    public function canAccessRawasTindakLanjut(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_rawas')
            || $this->hasRoleType('moderator_rawas')
            || $this->hasRoleType('viewer_rawas')
            || $this->hasRoleType('pic_rawas');
    }

    public function canCreateRawasTindakLanjutForButir(RawasButir $butir): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $hasAllowedRole = $this->hasRoleType('pic_rawas')
            || $this->hasRoleType('moderator_rawas');

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

    public function canAccessRawasReview(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_rawas')
            || $this->hasRoleType('moderator_rawas')
            || $this->hasRoleType('viewer_rawas')
            || $this->hasRoleType('pic_rawas');
    }

    public function canAccessRawasReport(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_rawas')
            || $this->hasRoleType('moderator_rawas')
            || $this->hasRoleType('pic_rawas')
            || $this->hasRoleType('viewer_rawas');
    }

    public function canReviewRawasByKomite(?int $komiteId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->hasRoleType('pic_rawas') && !$this->hasRoleType('moderator_rawas')) {
            return false;
        }

        if (empty($komiteId)) {
            return false;
        }

        return in_array($komiteId, $this->komiteIds());
    }

    public function canAccessDjsnPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('moderator_djsn')
            || $this->hasRoleType('viewer_djsn')
            || $this->hasRoleType('pic_djsn');
    }

    public function canCreateDjsnPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('moderator_djsn');
    }

    public function canRequestDeleteDjsnPerekaman(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('moderator_djsn');
    }

    public function isDjsnAdmin(): bool
    {
        return $this->hasRoleType('admin_djsn');
    }

    public function isDjsnModerator(): bool
    {
        return $this->hasRoleType('moderator_djsn');
    }

    public function canAccessDjsnTanggapan(): bool
    {
        return $this->hasRoleType('pic_djsn')
            || $this->hasRoleType('moderator_djsn')
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('viewer_djsn')
            || $this->isSuperAdmin();
    }

    public function canCreateDjsnTanggapanForButir(DjsnButir $butir): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $hasAllowedRole = $this->hasRoleType('pic_djsn')
            || $this->hasRoleType('moderator_djsn');

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

    public function canAccessDjsnReview(): bool
    {
        return $this->hasRoleType('pic_djsn')
            || $this->hasRoleType('moderator_djsn')
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('viewer_djsn')
            || $this->isSuperAdmin();
    }

    public function canReviewDjsnByKomite(?int $komiteId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->hasRoleType('pic_djsn') && !$this->hasRoleType('moderator_djsn')) {
            return false;
        }

        if (!$komiteId) {
            return false;
        }

        return in_array($komiteId, $this->komiteIds());
    }

    public function canCreateDjsnTindakLanjutForButir(DjsnButir $butir): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $hasAllowedRole = $this->hasRoleType('pic_djsn')
            || $this->hasRoleType('moderator_djsn');

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

    public function canAccessDjsnTindakLanjut(): bool
    {
        return $this->hasRoleType('pic_djsn')
            || $this->hasRoleType('moderator_djsn')
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('viewer_djsn')
            || $this->isSuperAdmin();
    }

    public function canAccessDjsnReport(): bool
    {
        return $this->hasRoleType('pic_djsn')
            || $this->hasRoleType('moderator_djsn')
            || $this->hasRoleType('admin_djsn')
            || $this->hasRoleType('viewer_djsn')
            || $this->isSuperAdmin();
    }

    public function canCreateRagabTindakLanjutForButir(RagabButir $butir): bool
    {
        if ($this->isSuperAdmin() || $this->hasRoleType('admin_ragab') || $this->hasRoleType('moderator_ragab')) {
            return true;
        }

        if (!$this->hasRoleType('pic_ragab')) {
            return false;
        }

        $userUnitKerjaIds = $this->unitKerjaIds();

        if (empty($userUnitKerjaIds)) {
            return false;
        }

        $picUnitKerjaIds = $butir->butirPics()
            ->where('jenis_pic', 'unit')
            ->whereNotNull('unit_kerja_id')
            ->pluck('unit_kerja_id')
            ->toArray();

        return count(array_intersect($userUnitKerjaIds, $picUnitKerjaIds)) > 0;
    }

    public function canCreateEksternalTindakLanjutForButir(EksternalButir $butir): bool
    {
        if ($this->isSuperAdmin() || $this->hasRoleType('admin_eksternal') || $this->hasRoleType('moderator_eksternal')) {
            return true;
        }

        if (!$this->hasRoleType('pic_eksternal')) {
            return false;
        }

        $userUnitKerjaIds = $this->unitKerjaIds();

        if (empty($userUnitKerjaIds)) {
            return false;
        }

        $picUnitKerjaIds = $butir->butirPics()
            ->where('jenis_pic', 'unit')
            ->whereNotNull('unit_kerja_id')
            ->pluck('unit_kerja_id')
            ->toArray();

        return count(array_intersect($userUnitKerjaIds, $picUnitKerjaIds)) > 0;
    }

    public function canAccessRagabReview(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab')
            || $this->hasRoleType('viewer_ragab')
            || $this->hasRoleType('pic_ragab');
    }

    public function canAccessRagabReport(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab')
            || $this->hasRoleType('pic_ragab')
            || $this->hasRoleType('viewer_ragab');
    }

    public function canAccessEksternalReview(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_eksternal')
            || $this->hasRoleType('moderator_eksternal')
            || $this->hasRoleType('viewer_eksternal')
            || $this->hasRoleType('pic_eksternal');
    }

    public function canAccessEksternalReport(): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_eksternal')
            || $this->hasRoleType('moderator_eksternal')
            || $this->hasRoleType('pic_eksternal')
            || $this->hasRoleType('viewer_eksternal');
    }

    // * Method ini boleh tetap ada untuk kompatibilitas lama, tapi RAGAB baru tidak bergantung komite.
    public function canReviewRagabByKomite(?int $komiteId): bool
    {
        return $this->isSuperAdmin()
            || $this->hasRoleType('admin_ragab')
            || $this->hasRoleType('moderator_ragab')
            || $this->hasRoleType('pic_ragab');
    }

    public function hasUnitKerjaSbd(): bool
    {
        return $this->unitKerja()
            ->wherePivot('status', 'active')
            ->where(function ($query) {
                $query->where('kode_unit', 'SBD')
                    ->orWhere('nama_unit', 'like', '%Sekretariat Badan%')
                    ->orWhere('nama_unit', 'like', '%Deputi Bidang Sekretariat Badan%');
            })
            ->exists();
    }

    public function canAccessSnpKompilasi(): bool
    {
        return $this->canAccessType('snp');
    }

    public function canCreateSnpKompilasi(): bool
    {
        return $this->isSuperAdmin()
            || (
                ($this->hasRoleType('admin_snp') || $this->hasRoleType('moderator_snp'))
                && $this->hasUnitKerjaSbd()
            );
    }
}
