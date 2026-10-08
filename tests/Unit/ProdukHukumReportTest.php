<?php

use App\Exports\ProdukHukumReportExport;
use App\Models\ProdukHukum;
use App\Models\Role;
use App\Models\RoleType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    config()->set('database.default', 'mysql');
    foreach (['mysql', 'mysql_produk_hukum'] as $connection) {
        config()->set('database.connections.'.$connection, array_merge(config('database.connections.sqlite'), ['database' => ':memory:']));
        DB::purge($connection);
    }
    foreach (['0001_01_01_000000_create_users_table.php', '2026_05_07_000000_create_main_custom_tables.php', '2026_06_23_093422_create_produk_hukum_tables.php'] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
    $roleType = RoleType::create(['role_id' => $role->id, 'name' => 'viewer_produk_hukum']);
    $this->viewer = User::factory()->create(['status' => 'active', 'name' => 'Penguji Rekap']);
    $this->viewer->roleTypes()->attach($roleType->id, ['status' => 'active']);
    $this->actingAs($this->viewer);
});

test('catalog searches all pages and statuses and exposes only list metadata', function () {
    for ($index = 1; $index <= 24; $index++) {
        ProdukHukum::create([
            'judul' => 'Arsip digital '.$index,
            'tahun_peraturan' => 2026,
            'nomor_peraturan_keputusan' => 'NOMOR-UJI-'.$index,
            'status_peraturan' => $index % 2 ? 'draft' : 'berlaku',
            'sifat_dokumen' => 'rahasia',
            'abstrak' => 'ISI RAHASIA JANGAN DIEKSPOR',
            'muatan_substansial' => 'SUBSTANSI TERBATAS',
        ]);
    }
    $response = $this->getJson(route('produk-hukum.report.options'))->assertOk()->assertJsonCount(20, 'data')
        ->assertJsonPath('total', 24)->assertJsonPath('last_page', 2);
    expect(array_keys($response->json('data.0')))->toBe([
        'id', 'kode_produk_hukum', 'judul', 'nomor_peraturan_keputusan', 'tahun_peraturan',
        'jenis_bentuk_peraturan', 'bidang_pengaturan', 'sifat_dokumen', 'status_peraturan',
    ]);
    $response->assertDontSee('ISI RAHASIA')->assertDontSee('SUBSTANSI TERBATAS');
    $this->getJson(route('produk-hukum.report.options', ['page' => 2]))->assertOk()->assertJsonCount(4, 'data');
    $this->getJson(route('produk-hukum.report.options', ['keyword' => 'NOMOR-UJI-24']))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.judul', 'Arsip digital 24');
    $this->getJson(route('produk-hukum.report.options', ['keyword' => 'Tidak ada']))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('produk-hukum.report.options', ['keyword' => ['invalid']]))->assertUnprocessable();
});

test('status exports include all matching records beyond list pagination and ignore unrelated filters', function (string $status, int $expectedCount) {
    foreach (['berlaku' => 11, 'tidak_berlaku' => 2, 'draft' => 3] as $value => $count) {
        for ($index = 0; $index < $count; $index++) {
            ProdukHukum::create(['judul' => 'Peraturan '.$value.' '.$index, 'tahun_peraturan' => 2026, 'status_peraturan' => $value]);
        }
    }
    Excel::fake();
    $this->postJson(route('produk-hukum.report.download'), [
        'mode' => 'status', 'status' => $status, 'format' => 'xlsx', 'keyword' => 'tidak cocok', 'page' => 2,
    ])->assertOk();
    Excel::matchByRegex();
    Excel::assertDownloaded('/Rekap_Produk_Hukum_'.$status.'_.*\.xlsx/', function (ProdukHukumReportExport $export) use ($expectedCount, $status): bool {
        $rows = array_slice($export->array(), 6);
        expect($rows)->toHaveCount($expectedCount);
        if ($status !== 'semua') {
            $label = ['berlaku' => 'Berlaku', 'tidak_berlaku' => 'Tidak Berlaku', 'draft' => 'Draf'][$status];
            expect(array_unique(array_column($rows, 8)))->toBe([$label]);
        }

        return true;
    });
})->with([['semua', 16], ['berlaku', 11], ['tidak_berlaku', 2], ['draft', 3]]);

test('manual export contains exactly the chosen regulations across statuses', function () {
    $products = collect(['berlaku', 'tidak_berlaku', 'draft'])->map(fn (string $status) => ProdukHukum::create([
        'judul' => 'Pilihan '.$status, 'status_peraturan' => $status, 'tahun_peraturan' => 2026,
    ]));
    Excel::fake();
    $this->postJson(route('produk-hukum.report.download'), [
        'mode' => 'manual', 'format' => 'xlsx', 'status' => 'tidak_berlaku', 'product_ids' => [$products[0]->id, $products[2]->id],
    ])->assertOk();
    Excel::matchByRegex();
    Excel::assertDownloaded('/Rekap_Produk_Hukum_pilihan_.*\.xlsx/', function (ProdukHukumReportExport $export): bool {
        expect(array_column(array_slice($export->array(), 6), 2))->toBe(['Pilihan berlaku', 'Pilihan draft']);

        return true;
    });
});

test('invalid or unavailable report selections are rejected', function (array $payload, string $field) {
    $this->postJson(route('produk-hukum.report.download'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['mode' => 'manual', 'format' => 'pdf', 'product_ids' => []], 'product_ids'],
    [['mode' => 'manual', 'format' => 'xlsx', 'product_ids' => [9999]], 'product_ids'],
    [['mode' => 'manual', 'format' => 'pdf', 'product_ids' => [1, 1]], 'product_ids.0'],
    [['mode' => 'manual', 'format' => 'pdf', 'product_ids' => ['wrong']], 'product_ids.0'],
    [['mode' => 'status', 'format' => 'pdf', 'status' => 'unknown'], 'status'],
    [['mode' => 'status', 'format' => 'pdf', 'status' => 'semua'], 'status'],
    [['mode' => 'status', 'format' => 'csv', 'status' => 'semua'], 'format'],
    [['mode' => 'unknown', 'format' => 'pdf'], 'mode'],
]);

test('both report endpoints enforce module permissions and authentication', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));
    $this->getJson(route('produk-hukum.report.options'))->assertForbidden();
    $this->postJson(route('produk-hukum.report.download'), ['mode' => 'status', 'status' => 'semua', 'format' => 'pdf'])->assertForbidden();
    auth()->forgetGuards();
    $this->getJson(route('produk-hukum.report.options'))->assertUnauthorized();
    $this->postJson(route('produk-hukum.report.download'))->assertUnauthorized();
});

test('report downloads require a valid session csrf token outside the test middleware bypass', function () {
    ProdukHukum::create(['judul' => 'Peraturan untuk uji token', 'tahun_peraturan' => 2026, 'status_peraturan' => 'berlaku']);
    $this->app['env'] = 'local';
    $this->withSession(['_token' => 'report-session-token']);
    $payload = ['mode' => 'status', 'status' => 'semua', 'format' => 'xlsx'];

    $this->postJson(route('produk-hukum.report.download'), $payload)->assertStatus(419);
    $this->postJson(route('produk-hukum.report.download'), $payload, ['X-CSRF-TOKEN' => 'incorrect-token'])->assertStatus(419);

    Excel::fake();
    $this->postJson(route('produk-hukum.report.download'), $payload, ['X-CSRF-TOKEN' => 'report-session-token'])->assertOk();
});

test('search treats sql fragments as literal search text', function () {
    ProdukHukum::create(['judul' => 'Peraturan biasa', 'tahun_peraturan' => 2026, 'status_peraturan' => 'berlaku']);
    $this->getJson(route('produk-hukum.report.options', ['keyword' => "' OR 1=1 --"]))
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('total', 0);
});

test('xlsx download preserves full text and identifiers with useful formatting and no executable formulas', function () {
    $product = ProdukHukum::create([
        'judul' => '=HYPERLINK("https://example.invalid") '.str_repeat('Judul panjang ', 40),
        'nomor_peraturan_keputusan' => '000123/2026', 'tahun_peraturan' => 2026,
        'status_peraturan' => 'draft', 'sifat_dokumen' => 'rahasia', 'abstrak' => 'RAHASIA TIDAK BOLEH MASUK',
    ]);
    $response = $this->postJson(route('produk-hukum.report.download'), ['mode' => 'manual', 'format' => 'xlsx', 'product_ids' => [$product->id]])
        ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = $response->baseResponse->getFile()->getPathname();
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        expect($sheet->getCell('C7')->getValue())->toBe($product->judul)
            ->and($sheet->getCell('C7')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('D7')->getValue())->toBe('000123/2026')
            ->and($sheet->getCell('E7')->getDataType())->toBe(DataType::TYPE_NUMERIC)
            ->and($sheet->getCell('I7')->getValue())->toBe('Draf')
            ->and($sheet->getCell('A4')->getValue())->toContain('Penguji Rekap', 'WIB')
            ->and($sheet->getFreezePane())->toBe('D7')
            ->and($sheet->getAutoFilter()->getRange())->toBe('A6:I7')
            ->and($sheet->getPageSetup()->getRowsToRepeatAtTop())->toBe(['6', '6'])
            ->and($sheet->getRowDimension(7)->getRowHeight())->toBeGreaterThan(100)
            ->and(json_encode($sheet->toArray()))->not->toContain('RAHASIA TIDAK BOLEH MASUK');
        if ($directory = getenv('PRODUK_HUKUM_REPORT_QA')) {
            copy($path, $directory.'/rekap-produk-hukum.xlsx');
            (new Html($sheet->getParent()))->save($directory.'/rekap-produk-hukum-excel.html');
        }
    } finally {
        @unlink($path);
    }
});

test('pdf download renders multiple landscape pages with full long titles and confidential metadata only', function () {
    for ($index = 1; $index <= 42; $index++) {
        ProdukHukum::create([
            'judul' => 'PERATURAN-UJI-'.$index.' '.($index === 17 ? str_repeat('Judul peraturan sangat panjang tentang pengelolaan arsip dan tata kelola. ', 45).'AKHIR-JUDUL-PANJANG' : 'Tata kelola arsip digital dan administrasi kelembagaan'),
            'tahun_peraturan' => 2026, 'nomor_peraturan_keputusan' => 'UJI/'.$index.'/2026',
            'jenis_bentuk_peraturan' => 'Peraturan Direksi', 'bidang_pengaturan' => 'Tata Kelola dan Administrasi',
            'status_peraturan' => 'berlaku', 'sifat_dokumen' => 'rahasia', 'abstrak' => 'ISI RAHASIA TERBATAS',
        ]);
    }
    $response = $this->postJson(route('produk-hukum.report.download'), ['mode' => 'status', 'status' => 'semua', 'format' => 'pdf'])
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-')->toContain('/MediaBox [0.000 0.000 841.890 595.280]');
    $html = view('layouts.produk-hukum.report-pdf', [
        'products' => ProdukHukum::all(), 'scope' => 'Semua status', 'printedBy' => 'Penguji Rekap',
        'printedAt' => '08/10/2026 10:00 WIB', 'statusLabels' => ['berlaku' => 'Berlaku'],
    ])->render();
    expect($html)->toContain('Lanjutan judul', 'AKHIR-JUDUL-PANJANG', 'SIDEWAS PRODUK HUKUM DEWAS', 'Penguji Rekap')
        ->not->toContain('ISI RAHASIA TERBATAS');
    if ($directory = getenv('PRODUK_HUKUM_REPORT_QA')) {
        file_put_contents($directory.'/rekap-produk-hukum.pdf', $response->getContent());
    }
});

test('report dialog is available to viewers and browser interaction preserves manual choices', function () {
    $response = $this->get(route('produk-hukum.index'))->assertOk()->assertSee('Download Rekap')->assertSee('Pilih peraturan sendiri');
    $manifestPath = public_path('build/manifest.json');
    if (! is_file($manifestPath)) {
        $this->markTestSkipped('Build frontend assets before running the browser test.');
    }
    $manifest = json_decode(file_get_contents($manifestPath), true);
    $html = view('layouts.produk-hukum.report-modal', [
        'statusStatistics' => ['berlaku' => 30, 'tidak_berlaku' => 2, 'draft' => 4],
        'statusOptions' => ['berlaku' => 'Berlaku', 'tidak_berlaku' => 'Tidak Berlaku', 'draft' => 'Draf'],
    ])->render();
    $process = new Process(['node', base_path('tests/Unit/ProdukHukumReport.browser.mjs')], base_path());
    $process->setInput(json_encode(['html' => $html, 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']]));
    $process->setTimeout(60)->mustRun();
    expect($process->getOutput())->toContain('Report browser checks passed');
});
