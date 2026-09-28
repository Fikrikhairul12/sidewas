<?php

use App\Models\ProdukHukum;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    session()->put('_token', 'produk-hukum-table-token');

    $this->produk = (new ProdukHukum)->forceFill([
        'id' => 42,
        'kode_produk_hukum' => 'PH-2026-0001',
        'judul' => 'Peraturan Direksi tentang Tata Kelola Arsip Digital Internal',
        'nomor_peraturan_keputusan' => 'DUMMY/01/2026',
        'tahun_peraturan' => 2026,
        'jenis_bentuk_peraturan' => 'Peraturan Direksi',
        'bidang_pengaturan' => 'Tata Kelola dan Administrasi',
        'sifat_dokumen' => 'publik',
        'status_peraturan' => 'berlaku',
        'files_count' => 2,
        'abstrak' => 'Isi lengkap hanya untuk halaman detail.',
    ]);

    $this->tableData = [
        'produkHukums' => new LengthAwarePaginator([$this->produk], 1, 8, 1, [
            'path' => route('produk-hukum.index'),
        ]),
        'canViewRahasiaProdukHukum' => false,
        'canDeleteProdukHukum' => false,
        'approvedAccessIds' => [],
        'pendingAccessIds' => [],
        'pendingDeleteIds' => [],
    ];
});

test('produk hukum table renders metadata in aligned columns and keeps detail navigation', function () {
    $view = $this->view('layouts.produk-hukum.table', $this->tableData);

    $view->assertSee($this->produk->judul)
        ->assertSee('PH-2026-0001')
        ->assertSee('2 file')
        ->assertSee('Bidang: Tata Kelola dan Administrasi')
        ->assertSee('href="'.route('produk-hukum.show', $this->produk).'"', false)
        ->assertDontSee($this->produk->abstrak);

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.(string) $view, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $headers = $xpath->query('//thead/tr/th');

    expect(array_map(fn (DOMNode $header): string => trim($header->textContent), iterator_to_array($headers)))
        ->toBe(['No.', 'Produk Hukum', 'Nomor', 'Tahun', 'Jenis / Bidang', 'Sifat', 'Status', 'Aksi']);

    $cells = $xpath->query('//tbody/tr/*');
    expect($cells->length)->toBe(8)
        ->and(trim($cells->item(0)->textContent))->toBe('1')
        ->and(trim($cells->item(2)->textContent))->toBe('DUMMY/01/2026')
        ->and(trim($cells->item(3)->textContent))->toBe('2026');
});

test('produk hukum table preserves document access states', function (
    string $visibility,
    bool $canViewRahasia,
    array $approvedIds,
    array $pendingIds,
    string $expectedAction,
) {
    $this->produk->sifat_dokumen = $visibility;

    $view = $this->view('layouts.produk-hukum.table', array_replace($this->tableData, [
        'canViewRahasiaProdukHukum' => $canViewRahasia,
        'approvedAccessIds' => $approvedIds,
        'pendingAccessIds' => $pendingIds,
    ]));

    $view->assertSee($expectedAction);
    $detailLink = 'href="'.route('produk-hukum.show', $this->produk).'"';
    $accessForm = 'action="'.route('produk-hukum.request-access', $this->produk->id).'"';

    if ($expectedAction === 'Detail') {
        $view->assertSee($detailLink, false)->assertDontSee($accessForm, false);
    } else {
        $view->assertDontSee($detailLink, false)
            ->assertSee('Detail lengkap dokumen ini bersifat rahasia.');

        if ($expectedAction === 'Ajukan Akses') {
            $view->assertSee($accessForm, false)
                ->assertSee('name="_token" value="produk-hukum-table-token"', false)
                ->assertSee('value="Mengajukan akses lihat produk hukum rahasia."', false);
        } else {
            $view->assertDontSee($accessForm, false)->assertSee('disabled', false);
        }
    }
})->with([
    'public viewer' => ['publik', false, [], [], 'Detail'],
    'restricted document' => ['rahasia', false, [], [], 'Ajukan Akses'],
    'pending access' => ['rahasia', false, [], [42], 'Menunggu Approval'],
    'approved access' => ['rahasia', false, [42], [], 'Detail'],
    'privileged access' => ['rahasia', true, [], [], 'Detail'],
    'approval takes precedence over pending request' => ['rahasia', false, [42], [42], 'Detail'],
]);

test('produk hukum table preserves delete permissions and pending requests', function (bool $canDelete, array $pendingIds) {
    $view = $this->view('layouts.produk-hukum.table', array_replace($this->tableData, [
        'canDeleteProdukHukum' => $canDelete,
        'pendingDeleteIds' => $pendingIds,
    ]));
    $deleteForm = 'action="'.route('produk-hukum.request-delete', $this->produk->id).'"';

    if ($canDelete && $pendingIds === []) {
        $view->assertSee($deleteForm, false)
            ->assertSee('Hapus')
            ->assertSee('method="POST"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token" value="produk-hukum-table-token"', false)
            ->assertSee("onsubmit=\"return confirm('Ajukan penghapusan Produk Hukum ini?')\"", false)
            ->assertSee('value="Mengajukan hapus Produk Hukum."', false);
    } else {
        $view->assertDontSee($deleteForm, false);

        if ($canDelete) {
            $view->assertSee('Menunggu Hapus')->assertSee('disabled', false);
        } else {
            $view->assertDontSee('Hapus');
        }
    }
})->with([
    'viewer' => [false, []],
    'viewer with pending request' => [false, [42]],
    'allowed to delete' => [true, []],
    'deletion already pending' => [true, [42]],
]);

test('produk hukum table keeps page numbering and filter query strings', function () {
    $paginator = new LengthAwarePaginator([$this->produk], 17, 8, 2, [
        'path' => route('produk-hukum.index'),
        'query' => ['keyword' => 'arsip', 'tahun_peraturan' => 2026],
    ]);
    $view = $this->view('layouts.produk-hukum.table', array_replace($this->tableData, [
        'produkHukums' => $paginator,
    ]));

    $view->assertSee(e($paginator->url(1)), false)
        ->assertSee(e($paginator->url(3)), false);

    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.(string) $view, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect(trim($xpath->query('//tbody/tr/td')->item(0)->textContent))->toBe('9');
});

test('produk hukum table handles empty results without offering record actions', function () {
    $view = $this->view('layouts.produk-hukum.table', array_replace($this->tableData, [
        'produkHukums' => new LengthAwarePaginator([], 0, 8),
        'canDeleteProdukHukum' => true,
    ]));

    $view->assertSee('Belum ada Produk Hukum.')
        ->assertSee('colspan="8"', false)
        ->assertDontSee('<form', false)
        ->assertDontSee('href=', false);
});

test('produk hukum table escapes document metadata', function () {
    $this->produk->judul = '<script>alert("judul")</script>';
    $this->produk->nomor_peraturan_keputusan = '<img src=x onerror=alert(1)>';

    $this->view('layouts.produk-hukum.table', $this->tableData)
        ->assertSee(e($this->produk->judul), false)
        ->assertSee(e($this->produk->nomor_peraturan_keputusan), false)
        ->assertDontSee('<script>', false)
        ->assertDontSee('<img', false);
});
