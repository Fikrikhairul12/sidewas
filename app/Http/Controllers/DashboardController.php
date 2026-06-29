<?php

namespace App\Http\Controllers;

use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\EksternalButir;
use App\Models\EksternalRecord;
use App\Models\LogActivity;
use App\Models\RagabButir;
use App\Models\RagabRecord;
use App\Models\RawasButir;
use App\Models\RawasRecord;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $modules = $this->modules();
        $allowedTypeCodes = collect(array_keys($modules))
            ->filter(fn (string $typeCode): bool => $user->canAccessType($typeCode))
            ->values();

        $selectedType = $request->string('jenis_rapat')->toString();
        if (! $allowedTypeCodes->contains($selectedType)) {
            $selectedType = '';
        }

        $activeTypeCodes = $selectedType !== ''
            ? collect([$selectedType])
            : $allowedTypeCodes;

        $filters = [
            'jenis_rapat' => $selectedType,
            'interval_bulan' => $request->string('interval_bulan', 'all')->toString(),
            'status' => $request->string('status')->toString(),
            'unit_kerja_id' => $request->integer('unit_kerja_id') ?: null,
        ];

        $moduleStats = $activeTypeCodes
            ->mapWithKeys(fn (string $typeCode): array => [
                $typeCode => $this->moduleStats($typeCode, $modules[$typeCode], $filters),
            ]);

        $chartData = [
            'suratPerJenis' => [
                'labels' => $moduleStats->pluck('label')->values(),
                'datasets' => [
                    [
                        'label' => 'Draft',
                        'data' => $moduleStats->pluck('status_surat.draft')->values(),
                        'backgroundColor' => '#94a3b8',
                    ],
                    [
                        'label' => 'Dalam Proses',
                        'data' => $moduleStats->pluck('status_surat.dalam_proses')->values(),
                        'backgroundColor' => '#c8e079',
                    ],
                    [
                        'label' => 'Selesai Tuntas',
                        'data' => $moduleStats->pluck('status_surat.tuntas')->values(),
                        'backgroundColor' => '#2377b9',
                    ],
                ],
            ],
        ];

        return view('dashboard', [
            'allowedTypes' => $allowedTypeCodes
                ->map(fn (string $typeCode): array => [
                    'code' => $typeCode,
                    'label' => $modules[$typeCode]['label'],
                ]),
            'attentionRows' => $this->attentionRows($activeTypeCodes, $modules, $filters, $user),
            'chartData' => $chartData,
            'filters' => $filters,
            'moduleStats' => $moduleStats,
            'recentActivities' => $this->recentActivities($activeTypeCodes),
            'unitKerjas' => UnitKerja::orderBy('nama_unit')->get(['id', 'kode_unit', 'nama_unit']),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function modules(): array
    {
        return [
            'snp' => [
                'label' => 'SNP',
                'color' => '#3b82f6',
                'record_model' => SnpRecord::class,
                'butir_model' => SnpButir::class,
                'record_key' => 'id_snp',
                'record_relation' => 'record',
                'butir_relation' => 'butirSnp',
                'butir_key' => 'id_butir_snp',
                'butir_text' => 'butir_snp',
            ],
            'ragab' => [
                'label' => 'Ragab',
                'color' => '#22c55e',
                'record_model' => RagabRecord::class,
                'butir_model' => RagabButir::class,
                'record_key' => 'id_ragab',
                'record_relation' => 'record',
                'butir_relation' => 'butirRagab',
                'butir_key' => 'id_butir_ragab',
                'butir_text' => 'keputusan_ragab',
            ],
            'rawas' => [
                'label' => 'Rawas',
                'color' => '#f59e0b',
                'record_model' => RawasRecord::class,
                'butir_model' => RawasButir::class,
                'record_key' => 'id_rawas',
                'record_relation' => 'record',
                'butir_relation' => 'butirRawas',
                'butir_key' => 'id_butir_rawas',
                'butir_text' => 'keputusan_rawas',
            ],
            'djsn' => [
                'label' => 'DJSN',
                'color' => '#8b5cf6',
                'record_model' => DjsnRecord::class,
                'butir_model' => DjsnButir::class,
                'record_key' => 'id_djsn',
                'record_relation' => 'record',
                'butir_relation' => 'butirDjsn',
                'butir_key' => 'id_butir_djsn',
                'butir_text' => 'butir_djsn',
            ],
            'eksternal' => [
                'label' => 'Rapat Eksternal',
                'color' => '#06b6d4',
                'record_model' => EksternalRecord::class,
                'butir_model' => EksternalButir::class,
                'record_key' => 'id_eksternal',
                'record_relation' => 'record',
                'butir_relation' => 'butirEksternal',
                'butir_key' => 'id_butir_eksternal',
                'butir_text' => 'keputusan_eksternal',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function moduleStats(string $typeCode, array $module, array $filters): array
    {
        $recordModel = $module['record_model'];
        $baseRecordQuery = $this->recordQuery($recordModel, $module, $filters);
        $totalSurat = (clone $baseRecordQuery)->count();
        $totalButir = $this->butirQuery($module, $filters)->count();

        $statusSurat = [
            'draft' => (clone $baseRecordQuery)->where('status', 'draft')->count(),
            'dalam_proses' => (clone $baseRecordQuery)->where('status', 'dalam_proses')->count(),
            'tuntas' => (clone $baseRecordQuery)->where('status', 'tuntas')->count(),
        ];

        $statusButir = [
            'terbit' => $this->countButirsByStatus($module, $filters, 'terbit'),
            'dalam_proses' => $this->countButirsByStatus($module, $filters, 'dalam_proses'),
            'diusulkan_tuntas' => $this->countButirsByStatus($module, $filters, 'diusulkan_tuntas'),
            'selesai_tuntas' => $this->countButirsByStatus($module, $filters, 'selesai_tuntas'),
        ];

        return [
            'code' => $typeCode,
            'label' => $module['label'],
            'color' => $module['color'],
            'total_surat' => $totalSurat,
            'total_butir' => $totalButir,
            'jatuh_tempo' => $this->countOverdueButirs($module, $filters),
            'progress' => $totalButir > 0 ? round(($statusButir['selesai_tuntas'] / $totalButir) * 100, 1) : 0,
            'status_surat' => $statusSurat,
            'status_butir' => $statusButir,
        ];
    }

    /**
     * @param  class-string  $recordModel
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $filters
     */
    private function recordQuery(string $recordModel, array $module, array $filters)
    {
        $query = $recordModel::query();
        $this->applyRecordFilters($query, $module, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $filters
     */
    private function butirQuery(array $module, array $filters)
    {
        $butirModel = $module['butir_model'];
        $query = $butirModel::query();

        $query->whereHas($module['record_relation'], function ($recordQuery) use ($module, $filters): void {
            $this->applyRecordFilters($recordQuery, $module, $filters);
        });

        if (! empty($filters['unit_kerja_id'])) {
            $query->whereHas('butirPics', function ($picQuery) use ($filters): void {
                $picQuery->where('unit_kerja_id', $filters['unit_kerja_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $filters
     */
    private function countButirsByStatus(array $module, array $filters, string $status): int
    {
        return (clone $this->butirQuery($module, $filters))
            ->where('status', $status)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $filters
     */
    private function countOverdueButirs(array $module, array $filters): int
    {
        return (clone $this->butirQuery($module, $filters))
            ->where('status', '!=', 'selesai_tuntas')
            ->whereHas($module['record_relation'], function ($recordQuery): void {
                $recordQuery->whereDate('jth_tempo', '<', now()->toDateString());
            })
            ->count();
    }

    /**
     * @param  array<int, string>  $activeTypeCodes
     * @param  array<string, array<string, mixed>>  $modules
     * @param  array<string, mixed>  $filters
     */
    private function attentionRows($activeTypeCodes, array $modules, array $filters, User $sender)
    {
        return collect($activeTypeCodes)
            ->flatMap(function (string $typeCode) use ($modules, $filters, $sender) {
                $module = $modules[$typeCode];

                return $this->butirQuery($module, $filters)
                    ->with([$module['record_relation'], 'butirPics'])
                    ->where(function ($query) use ($module): void {
                        $query
                            ->whereHas($module['record_relation'], function ($recordQuery): void {
                                $recordQuery->whereDate('jth_tempo', '<=', now()->addDays(14)->toDateString());
                            })
                            ->orWhereIn('status', ['dalam_proses', 'diusulkan_tuntas']);
                    })
                    ->where('status', '!=', 'selesai_tuntas')
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function ($butir) use ($typeCode, $module, $sender): array {
                        $record = $butir->{$module['record_relation']};
                        $recipientEmails = $this->picEmailsForButir($butir);

                        return [
                            'id' => $record->{$module['record_key']},
                            'jenis' => $module['label'],
                            'butir' => $butir->{$module['butir_key']},
                            'perihal' => str($record->perihal_surat ?? '-')->limit(60)->toString(),
                            'status' => $this->statusLabel($butir->status),
                            'status_class' => $this->statusClass($butir->status),
                            'jatuh_tempo' => optional($record->jth_tempo)->format('d/m/Y') ?? '-',
                            'reminder_mailto' => $this->reminderMailto($recipientEmails, $sender, $record, $butir, $module),
                            'reminder_gmail_url' => $this->reminderGmailUrl($recipientEmails, $sender, $record, $butir, $module),
                            'reminder_recipients' => $recipientEmails,
                            'timestamp' => optional($record->jth_tempo)->timestamp ?? PHP_INT_MAX,
                            'type_code' => $typeCode,
                        ];
                    });
            })
            ->sortBy('timestamp')
            ->take(6)
            ->values();
    }

    private function picEmailsForButir($butir): array
    {
        $butir->loadMissing('butirPics');

        $unitKerjaIds = $butir->butirPics
            ->pluck('unit_kerja_id')
            ->filter()
            ->unique()
            ->values();

        $komiteIds = $butir->butirPics
            ->pluck('komite_id')
            ->filter()
            ->unique()
            ->values();

        if ($unitKerjaIds->isEmpty() && $komiteIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereNotNull('email')
            ->where(function ($query) use ($unitKerjaIds, $komiteIds): void {
                if ($unitKerjaIds->isNotEmpty()) {
                    $query->whereHas('unitKerja', function ($unitQuery) use ($unitKerjaIds): void {
                        $unitQuery
                            ->where('tb_user_unit_kerja.status', 'active')
                            ->whereIn('tb_unit_kerja.id', $unitKerjaIds);
                    });
                }

                if ($komiteIds->isNotEmpty()) {
                    $query->orWhereHas('komite', function ($komiteQuery) use ($komiteIds): void {
                        $komiteQuery
                            ->where('tb_user_komite.status', 'active')
                            ->whereIn('tb_komite.id', $komiteIds);
                    });
                }
            })
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $recipientEmails
     * @param  array<string, mixed>  $module
     */
    private function reminderMailto(array $recipientEmails, User $sender, $record, $butir, array $module): ?string
    {
        if (empty($recipientEmails)) {
            return null;
        }

        $subject = 'Pengingat tindak lanjut ' . $butir->{$module['butir_key']};
        $body = implode("\n", [
            'Yth. PIC terkait,',
            '',
            'Mohon ditindaklanjuti butir pengawasan berikut:',
            'Jenis Pengawasan: ' . $module['label'],
            'No Surat: ' . $record->{$module['record_key']},
            'ID Butir: ' . $butir->{$module['butir_key']},
            'Perihal: ' . ($record->perihal_surat ?? '-'),
            'Status: ' . $this->statusLabel($butir->status),
            'Jatuh Tempo: ' . (optional($record->jth_tempo)->format('d/m/Y') ?? '-'),
            '',
            'Dikirim oleh: ' . $sender->email,
        ]);

        $query = http_build_query([
            'cc' => $sender->email,
            'subject' => $subject,
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'mailto:' . implode(',', $recipientEmails) . '?' . $query;
    }

    /**
     * @param  array<int, string>  $recipientEmails
     * @param  array<string, mixed>  $module
     */
    private function reminderGmailUrl(array $recipientEmails, User $sender, $record, $butir, array $module): ?string
    {
        if (empty($recipientEmails)) {
            return null;
        }

        $subject = 'Pengingat tindak lanjut ' . $butir->{$module['butir_key']};
        $body = implode("\n", [
            'Yth. PIC terkait,',
            '',
            'Mohon ditindaklanjuti butir pengawasan berikut:',
            'Jenis Pengawasan: ' . $module['label'],
            'No Surat: ' . $record->{$module['record_key']},
            'ID Butir: ' . $butir->{$module['butir_key']},
            'Perihal: ' . ($record->perihal_surat ?? '-'),
            'Status: ' . $this->statusLabel($butir->status),
            'Jatuh Tempo: ' . (optional($record->jth_tempo)->format('d/m/Y') ?? '-'),
            '',
            'Dikirim oleh: ' . $sender->email,
        ]);

        return 'https://mail.google.com/mail/?view=cm&fs=1&' . http_build_query([
            'to' => implode(',', $recipientEmails),
            'cc' => $sender->email,
            'su' => $subject,
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function recentActivities($activeTypeCodes)
    {
        return LogActivity::with('user')
            ->whereIn('type_code', collect($activeTypeCodes)->values()->all())
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  array<string, mixed>  $filters
     */
    private function applyRecordFilters($query, array $module, array $filters): void
    {
        if (! empty($filters['interval_bulan']) && $filters['interval_bulan'] !== 'all') {
            [$startMonth, $endMonth] = array_map('intval', explode('-', $filters['interval_bulan']));
            $query->whereMonth('tanggal_surat', '>=', $startMonth)
                ->whereMonth('tanggal_surat', '<=', $endMonth);
        }

        if (! empty($filters['unit_kerja_id'])) {
            $query->whereHas($module['butir_relation'] . '.butirPics', function ($picQuery) use ($filters): void {
                $picQuery->where('unit_kerja_id', $filters['unit_kerja_id']);
            });
        }

        if (! empty($filters['status'])) {
            $query->whereHas($module['butir_relation'], function ($butirQuery) use ($filters): void {
                $butirQuery->where('status', $filters['status']);
            });
        }
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'terbit' => 'Terbit',
            'dalam_proses', 'dalam_proses_tindak_lanjut', 'dalam_proses_reviu_dewas', 'dalam_proses_tindak_lanjut_direksi' => 'Dalam Proses',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'selesai', 'tuntas', 'selesai_tuntas' => 'Selesai Tuntas',
            default => str($status ?? '-')->replace('_', ' ')->title()->toString(),
        };
    }

    private function statusClass(?string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-100 text-slate-700',
            'terbit' => 'bg-blue-100 text-blue-700',
            'diusulkan_tuntas' => 'bg-purple-100 text-purple-700',
            'selesai', 'tuntas', 'selesai_tuntas' => 'bg-green-100 text-green-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }
}
