<?php

namespace App\Services;

use App\Enums\SuratStatus;
use App\Models\DjsnRecord;
use App\Models\SnpRecord;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SuratAwalStatusService
{
    public function enabled(string $connection): bool
    {
        return DB::connection($connection)->getSchemaBuilder()->hasColumn('tb_record', 'pernah_terbit');
    }

    public function batasAwal(string|CarbonInterface|null $tanggalSurat): ?Carbon
    {
        return $tanggalSurat === null ? null : Carbon::parse($tanggalSurat, 'Asia/Jakarta')->startOfDay()->addDays(14);
    }

    public function synchronizeModule(string $module): int
    {
        $connection = $this->connection($module);
        if (! $this->enabled($connection)) {
            return 0;
        }

        $changed = 0;
        DB::connection($connection)->table('tb_record')->select('id')->orderBy('id')
            ->chunkById(100, function ($records) use ($module, &$changed): void {
                foreach ($records as $record) {
                    $changed += (int) $this->synchronizeId($module, $record->id)['changed'];
                }
            });

        return $changed;
    }

    public function synchronizeRecord(SnpRecord|DjsnRecord $record): void
    {
        $connection = $record->getConnectionName();
        if (! $record->exists || ! $this->enabled($connection)) {
            return;
        }

        $result = $this->synchronizeId($connection === 'mysql_snp' ? 'snp' : 'djsn', (int) $record->getKey());
        if ($result['status'] !== null) {
            $record->setAttribute('status', $result['status']);
            $record->setAttribute('pernah_terbit', $result['pernah_terbit']);
            $record->syncOriginalAttributes(['status', 'pernah_terbit']);
        }
    }

    /** @return array{changed: bool, status: ?string, pernah_terbit: bool} */
    private function synchronizeId(string $module, int $id): array
    {
        $db = DB::connection($this->connection($module));

        return $db->transaction(function () use ($db, $module, $id): array {
            $record = $db->table('tb_record')->where('id', $id)->lockForUpdate()->first();
            if ($record === null) {
                return ['changed' => false, 'status' => null, 'pernah_terbit' => false];
            }

            $recordColumn = 'id_'.$module;
            $butirColumn = 'id_butir_'.$module;
            $hasButir = $db->table('tb_butir_'.$module)->where($recordColumn, $record->{$recordColumn})->exists();
            $everPublished = (bool) $record->pernah_terbit || $hasButir || ($record->putaran_aktif_id ?? null) !== null;
            $status = $record->status;

            if (in_array($status, $this->initialValues(), true)) {
                $hasResponse = $db->table('tb_tanggapan as t')
                    ->join('tb_butir_'.$module.' as b', 't.'.$butirColumn, '=', 'b.'.$butirColumn)
                    ->where('b.'.$recordColumn, $record->{$recordColumn})
                    ->whereNotNull('t.tanggapan')->whereRaw("TRIM(t.tanggapan) <> ''")->exists();
                $deadline = $this->batasAwal($record->tanggal_surat);

                $status = match (true) {
                    $hasResponse => SuratStatus::Proses->value,
                    $everPublished => SuratStatus::Terbit->value,
                    $deadline !== null && $deadline->lessThanOrEqualTo(now('Asia/Jakarta')->startOfDay()) => SuratStatus::BelumDitindaklanjuti->value,
                    default => SuratStatus::Draft->value,
                };
            }

            $changes = [];
            if ($record->status !== $status) {
                $changes['status'] = $status;
            }
            if ((bool) $record->pernah_terbit !== $everPublished) {
                $changes['pernah_terbit'] = $everPublished;
            }
            if ($changes !== []) {
                $db->table('tb_record')->where('id', $id)->update($changes);
            }

            return ['changed' => $changes !== [], 'status' => $status, 'pernah_terbit' => $everPublished];
        }, 3);
    }

    /** @return list<string> */
    public function initialValues(): array
    {
        return ['draft', 'terbit', 'belum_ditindaklanjuti', 'proses', 'dalam_proses'];
    }

    /** @return array<string, int> */
    public function statistics(string $module): array
    {
        $counts = DB::connection($this->connection($module))->table('tb_record')
            ->selectRaw('status, COUNT(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');
        $values = array_fill_keys(array_keys(SuratStatus::options()), 0);

        return ['total' => (int) $counts->sum(), ...$values, ...$counts->map(fn ($value): int => (int) $value)->all()];
    }

    /** @return array<string, string> */
    public function options(string $module): array
    {
        $options = SuratStatus::options();
        if (DB::connection($this->connection($module))->table('tb_record')->where('status', 'tuntas')->exists()) {
            $options['tuntas'] = 'Tuntas (Alur Lama)';
        }

        return $options;
    }

    public function applyFilter(Builder|\Illuminate\Database\Eloquent\Builder $query, string $status): void
    {
        $query->where('status', $status === 'dalam_proses' ? SuratStatus::Proses->value : $status);
    }

    private function connection(string $module): string
    {
        if (! in_array($module, ['snp', 'djsn'], true)) {
            throw new InvalidArgumentException('Status awal surat hanya tersedia untuk SNP atau DJSN.');
        }

        return 'mysql_'.$module;
    }
}
