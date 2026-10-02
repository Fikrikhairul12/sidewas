<?php

use App\Models\ProdukHukum;
use App\Models\ProdukHukumFile;
use App\Models\ProdukHukumJenisPeraturan;
use App\Models\Role;
use App\Models\RoleType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
    $role = Role::create(['name' => 'super_admin', 'display_name' => 'Super Admin', 'is_universal' => true]);
    $roleType = RoleType::create(['role_id' => $role->id, 'name' => 'super_admin']);
    $this->superAdmin = User::factory()->create(['status' => 'active']);
    $this->superAdmin->roleTypes()->attach($roleType->id, ['status' => 'active']);
    $this->actingAs($this->superAdmin);
    ProdukHukumJenisPeraturan::create(['nama' => 'Peraturan Direksi', 'singkatan' => 'PERDIR', 'is_active' => true]);
});

test('status statistics count all regulations beyond pagination and count documents instead of files', function () {
    foreach (['berlaku' => 11, 'tidak_berlaku' => 2, 'draft' => 3] as $status => $total) {
        for ($index = 0; $index < $total; $index++) {
            $product = ProdukHukum::create(['judul' => 'Peraturan '.$status.' '.$index, 'tahun_peraturan' => 2026, 'status_peraturan' => $status]);
        }
    }
    foreach (['lampiran-a.pdf', 'lampiran-b.pdf'] as $name) {
        ProdukHukumFile::create(['produk_hukum_id' => $product->id, 'nama_file' => $name]);
    }
    $response = $this->get(route('produk-hukum.index'))->assertOk()
        ->assertViewHas('statusStatistics', ['berlaku' => 11, 'tidak_berlaku' => 2, 'draft' => 3])
        ->assertViewHas('produkHukums', fn ($items) => $items->total() === 16 && $items->count() === 8)
        ->assertSee('Statistik Peraturan')->assertSee('Semua Status');

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $cards = $xpath->query('//section[@aria-labelledby="statistik-peraturan-title"]//dl/div');
    expect($cards->length)->toBe(3);
    expect(trim($xpath->query('.//dt', $cards->item(0))->item(0)->textContent))->toBe('Berlaku');
    expect(trim($xpath->query('.//dd', $cards->item(0))->item(0)->textContent))->toBe('11');
    expect(trim($xpath->query('.//dd', $cards->item(1))->item(0)->textContent))->toBe('2');
    expect(trim($xpath->query('.//dd', $cards->item(2))->item(0)->textContent))->toBe('3');
    expect($xpath->query('//form[@method="GET"]//div[contains(@class, "xl:grid-cols-5")]/div/select[@name="status_peraturan"]')->length)->toBe(1);
});

test('status filter selects the requested status and leaves overview totals unchanged', function (string $status) {
    foreach (['berlaku', 'tidak_berlaku', 'draft'] as $value) {
        ProdukHukum::create(['judul' => 'Peraturan '.$value, 'tahun_peraturan' => 2026, 'status_peraturan' => $value]);
    }
    $response = $this->get(route('produk-hukum.index', ['status_peraturan' => $status]))->assertOk()
        ->assertViewHas('statusStatistics', ['berlaku' => 1, 'tidak_berlaku' => 1, 'draft' => 1])
        ->assertViewHas('produkHukums', fn ($items) => $items->total() === 1 && $items->first()->status_peraturan === $status);

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//select[@id="filter-status-peraturan"]/option[@selected]')->item(0)->getAttribute('value'))->toBe($status);
})->with(['berlaku', 'tidak_berlaku', 'draft']);

test('status works together with all existing filters and is retained by pagination', function () {
    $attributes = ['judul' => 'Arsip Digital', 'tahun_peraturan' => 2026, 'bidang_pengaturan' => 'Administrasi', 'jenis_bentuk_peraturan' => 'Peraturan Direksi', 'status_peraturan' => 'draft'];
    for ($index = 0; $index < 9; $index++) {
        ProdukHukum::create($attributes);
    }
    foreach (['judul' => 'Layanan', 'tahun_peraturan' => 2025, 'bidang_pengaturan' => 'Keuangan', 'jenis_bentuk_peraturan' => 'Keputusan Direksi', 'status_peraturan' => 'berlaku'] as $field => $value) {
        ProdukHukum::create(array_replace($attributes, [$field => $value]));
    }
    $filters = ['keyword' => 'Arsip', 'tahun_peraturan' => 2026, 'bidang_pengaturan' => 'Administrasi', 'jenis_bentuk_peraturan' => 'Peraturan Direksi', 'status_peraturan' => 'draft'];
    $this->get(route('produk-hukum.index', $filters))->assertOk()
        ->assertViewHas('produkHukums', function ($items) use ($filters) {
            parse_str(parse_url($items->nextPageUrl(), PHP_URL_QUERY), $query);
            foreach ($filters as $key => $value) {
                expect((string) $query[$key])->toBe((string) $value);
            }

            return $items->total() === 9 && $items->count() === 8;
        });
    $this->get(route('produk-hukum.index', $filters + ['page' => 2]))->assertOk()
        ->assertViewHas('produkHukums', fn ($items) => $items->total() === 9 && $items->count() === 1);
    $this->get(route('produk-hukum.index'))->assertOk()->assertViewHas('produkHukums', fn ($items) => $items->total() === 14);
});

test('empty statuses display zero and an empty status filter includes all records', function () {
    $this->get(route('produk-hukum.index'))->assertOk()
        ->assertViewHas('statusStatistics', ['berlaku' => 0, 'tidak_berlaku' => 0, 'draft' => 0]);
    ProdukHukum::create(['judul' => 'Draf saja', 'status_peraturan' => 'draft']);
    $this->get(route('produk-hukum.index', ['status_peraturan' => 'berlaku']))->assertOk()
        ->assertViewHas('produkHukums', fn ($items) => $items->isEmpty())
        ->assertViewHas('statusStatistics', ['berlaku' => 0, 'tidak_berlaku' => 0, 'draft' => 1]);
    $this->get(route('produk-hukum.index', ['status_peraturan' => '']))->assertOk()
        ->assertViewHas('produkHukums', fn ($items) => $items->total() === 1);
});

test('invalid status filter is rejected in its own error bag', function () {
    $this->from(route('produk-hukum.index'))->get(route('produk-hukum.index', ['status_peraturan' => 'unknown']))
        ->assertRedirect(route('produk-hukum.index'))->assertSessionHasErrors(['status_peraturan'], null, 'filters');
    $this->get(route('produk-hukum.index'))->assertOk()->assertSee('role="alert"', false);
    $this->getJson(route('produk-hukum.index', ['status_peraturan' => ['draft']]))->assertUnprocessable()->assertJsonValidationErrors('status_peraturan');
});

test('viewer sees counts and status filtering without gaining access to confidential contents', function () {
    $role = Role::create(['name' => 'viewer', 'display_name' => 'Viewer']);
    $roleType = RoleType::create(['role_id' => $role->id, 'name' => 'viewer_produk_hukum']);
    $viewer = User::factory()->create(['status' => 'active']);
    $viewer->roleTypes()->attach($roleType->id, ['status' => 'active']);
    $product = ProdukHukum::create(['judul' => 'Dokumen rahasia', 'status_peraturan' => 'draft', 'sifat_dokumen' => 'rahasia', 'abstrak' => 'Isi tidak boleh ditampilkan']);
    $this->actingAs($viewer)->get(route('produk-hukum.index', ['status_peraturan' => 'draft']))->assertOk()
        ->assertViewHas('statusStatistics', ['berlaku' => 0, 'tidak_berlaku' => 0, 'draft' => 1])
        ->assertSee('Ajukan Akses')->assertDontSee('Isi tidak boleh ditampilkan')->assertDontSee('Tambah Produk Hukum');
    $this->get(route('produk-hukum.show', $product))->assertForbidden();
    $this->actingAs(User::factory()->create(['status' => 'active']))->get(route('produk-hukum.index'))->assertForbidden();
});
