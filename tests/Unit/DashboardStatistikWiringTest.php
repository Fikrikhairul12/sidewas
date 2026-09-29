<?php

use App\Http\Controllers\DashboardController;
use App\View\Components\AppLayout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Component::resolveComponentsUsing(function (string $component, array $data): Component {
        if ($component === AppLayout::class) {
            return new class extends Component
            {
                public function render(): string
                {
                    return '{{ $slot }}';
                }
            };
        }

        return new $component(...$data);
    });

    $this->dashboardData = [
        'allowedTypes' => collect([['code' => 'snp', 'label' => 'SNP']]),
        'filters' => [
            'jenis_rapat' => 'snp',
            'interval_bulan' => '7-9',
            'status' => 'dalam_proses',
            'unit_kerja_id' => 7,
        ],
        'unitKerjas' => collect([(object) ['id' => 7, 'kode_unit' => 'U07', 'nama_unit' => 'Unit Pengawasan']]),
        'moduleStats' => collect([
            [
                'label' => 'SNP',
                'total_butir' => 10,
                'progress' => 40,
                'status_butir' => ['terbit' => 1, 'dalam_proses' => 3, 'diusulkan_tuntas' => 2, 'selesai_tuntas' => 4],
            ],
        ]),
        'recentActivities' => collect([
            (object) [
                'type_code' => 'snp',
                'description' => 'User memperbarui tindak lanjut surat SNP.',
                'user' => (object) ['name' => 'Pengguna Statistik'],
                'created_at' => Carbon::parse('2026-09-28 09:33'),
            ],
        ]),
        'attentionRows' => collect([
            [
                'jenis' => 'SNP',
                'id' => 'B/14/012026-SNP',
                'perihal' => 'Kasus Fraud Klaim Jaminan Kecelakaan Kerja (JKK)',
                'butir' => 'B/14/012026-SNP.05',
                'status' => 'Dalam Proses',
                'status_class' => 'bg-amber-100 text-amber-700',
                'jatuh_tempo' => '02/02/2026',
                'reminder_gmail_url' => 'https://mail.google.com/mail/?view=cm&to=pic@example.test',
                'reminder_recipients' => ['pic@example.test'],
            ],
        ]),
        'chartData' => [
            'suratPerJenis' => [
                'labels' => ['SNP'],
                'datasets' => [['label' => 'Dalam Proses', 'data' => [6], 'backgroundColor' => '#c8e079']],
            ],
        ],
    ];
});

afterEach(function (): void {
    Component::forgetComponentsResolver();
});

test('dashboard route uses statistik controller', function () {
    $route = Route::getRoutes()->getByName('dashboard');

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toBe(DashboardController::class.'@index');
});

test('dashboard view exposes chart canvases and data payload', function () {
    $view = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($view)
        ->toContain('suratPerJenisChart')
        ->toContain('Statistik Tindak Lanjut Hasil Pengawasan')
        ->toContain('butirProgressStatuses')
        ->toContain('dashboard-chart-data');
});

test('dashboard chart script loads chart js', function () {
    $script = file_get_contents(resource_path('js/dashboard-chart.js'));

    expect($script)
        ->toContain("import Chart from 'chart.js/auto'")
        ->toContain('makeBarChart')
        ->toContain('stacked: Boolean(dataset.datasets)');
});

test('dashboard rearranges panels while preserving statistics filters activities and reminders', function (): void {
    $view = $this->view('dashboard', $this->dashboardData);

    $view->assertSeeInOrder([
        'Statistik Hasil Pengawasan',
        'Statistik Tindak Lanjut Hasil Pengawasan',
        'Aktivitas Terbaru',
        'Status Tindak Lanjut yang Perlu Perhatian',
    ])
        ->assertSee('10 butir')
        ->assertSee('40,0% selesai tuntas')
        ->assertSee('User memperbarui tindak lanjut surat SNP.')
        ->assertSee('Oleh Pengguna Statistik')
        ->assertSee('28/09/2026 09:33')
        ->assertDontSee('Kunjungan Kerja');

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.(string) $view, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $chartPanel = $xpath->query('//section[@aria-labelledby="statistik-hasil-heading"]')->item(0);
    $progressPanel = $xpath->query('//section[@aria-labelledby="statistik-tindak-lanjut-heading"]')->item(0);
    $activityPanel = $xpath->query('//section[@aria-labelledby="aktivitas-terbaru-heading"]')->item(0);
    $attentionPanel = $xpath->query('//section[@aria-labelledby="tindak-lanjut-perhatian-heading"]')->item(0);

    expect($chartPanel->parentNode->isSameNode($attentionPanel->parentNode))->toBeTrue()
        ->and($progressPanel->parentNode->isSameNode($activityPanel->parentNode))->toBeTrue()
        ->and($progressPanel->parentNode->parentNode->isSameNode($chartPanel->parentNode))->toBeTrue();

    foreach ($this->dashboardData['filters'] as $name => $value) {
        $selectedOption = $xpath->query('//select[@name="'.$name.'"]/option[@selected]');

        expect($selectedOption->length)->toBe(1)
            ->and($selectedOption->item(0)->getAttribute('value'))->toBe((string) $value);
    }

    $statusCounts = $xpath->query('.//strong', $progressPanel);
    expect(array_map(fn (DOMNode $node): string => trim($node->textContent), iterator_to_array($statusCounts)))
        ->toBe(['1', '3', '2', '4']);

    $row = $this->dashboardData['attentionRows']->first();
    $cells = $xpath->query('//tbody/tr/td');
    expect($cells->length)->toBe(6)
        ->and(trim($cells->item(0)->textContent))->toBe($row['jenis'])
        ->and($cells->item(1)->textContent)->toContain($row['id'], $row['perihal'])
        ->and(trim($cells->item(2)->textContent))->toBe($row['butir'])
        ->and(trim($cells->item(3)->textContent))->toBe($row['status'])
        ->and(trim($cells->item(4)->textContent))->toBe($row['jatuh_tempo'])
        ->and($xpath->query('//tbody/tr/td/a')->item(0)->getAttribute('href'))->toBe($row['reminder_gmail_url']);

    $chartPayload = $xpath->query('//script[@id="dashboard-chart-data"]')->item(0)->textContent;
    expect(json_decode($chartPayload, true))->toBe($this->dashboardData['chartData']);
});

test('dashboard keeps empty panels and unavailable reminder states', function (): void {
    $row = $this->dashboardData['attentionRows']->first();
    $row['reminder_gmail_url'] = null;
    $row['reminder_recipients'] = [];

    $this->view('dashboard', array_replace($this->dashboardData, ['attentionRows' => collect([$row])]))
        ->assertSee('disabled', false)
        ->assertSee('Belum ada email PIC aktif untuk butir ini.')
        ->assertDontSee('href="https://mail.google.com/', false);

    $this->view('dashboard', array_replace($this->dashboardData, [
        'moduleStats' => collect(),
        'recentActivities' => collect(),
        'attentionRows' => collect(),
    ]))
        ->assertSee('Belum ada modul yang bisa ditampilkan.')
        ->assertSee('Belum ada aktivitas terbaru.')
        ->assertSee('Belum ada data yang perlu perhatian.');
});
