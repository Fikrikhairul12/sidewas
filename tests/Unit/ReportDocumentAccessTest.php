<?php

use App\Enums\TahapProses;
use App\Models\User;
use App\Services\KompilasiBahanReader;
use App\Services\SuratTanggalService;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

uses(TestCase::class);
require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    $this->travelTo(now()->setDate(2026, 11, 1)->setTime(10, 30));
    Storage::fake('public');
});

function bugReportPdf(string $text): string
{
    $pdf = new Dompdf;
    $pdf->loadHtml('<p>'.htmlspecialchars($text).'</p>');
    $pdf->render();

    return $pdf->output();
}

function bugReportSubmission(array $f, string $label): Model
{
    $stage = $f['butir']->record->fresh()->tahapAktif;
    $followUp = $stage?->tahap_proses === TahapProses::TindakLanjut;
    test()->actingAs($f['actor'])->post($followUp ? route($f['module'].'.tindak-lanjut.store') : route($f['module'].'.tanggapan.store', $f['butir']), [
        'butir_id' => $f['butir']->id, 'tahap_id' => $stage?->id,
        'butir_pic_id' => $f['butir']->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id,
        'isi' => 'Tanggapan '.$label, 'deliverables' => 'Hasil '.$label,
        'dokumen' => UploadedFile::fake()->createWithContent('bukti.pdf', bugReportPdf($label)),
    ])->assertRedirect()->assertSessionHasNoErrors();

    return ($followUp ? $f['butir']->tindakLanjuts() : $f['butir']->tanggapanUnit())->latest('id')->firstOrFail();
}

function bugReportCompilation(array $f, string $label): Model
{
    $stage = $f['butir']->record->fresh()->tahapAktif;
    test()->actingAs($f['actor'])->post(route($f['module'].'.kompilasi.store', $f['butir']), [
        'tahap_id' => $stage?->id, 'putaran_id' => $stage?->putaran_asal_id,
        'tahap_kompilasi' => $stage?->tahap_proses === TahapProses::TindakLanjut ? 'tindak_lanjut' : 'tanggapan',
        'hasil_kompilasi' => 'Kompilasi '.$label,
        'dokumen' => UploadedFile::fake()->createWithContent('kompilasi.pdf', bugReportPdf('Kompilasi '.$label)),
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $f['butir']->reviews()->latest('id')->firstOrFail();
}

function bugReportFixture(string $module): array
{
    $f = ['module' => $module, 'actor' => workflowUser('super_admin'), 'butir' => workflowButir($module)];
    $f['source'] = bugReportSubmission($f, 'BAHAN AWAL');
    $f['review'] = bugReportCompilation($f, 'AWAL');
    $f['compilation'] = $f['review']->kompilasiPutaran;
    $f['copy'] = $f['compilation']->bahan()->firstOrFail()->salinan['dokumen'];
    $f['url'] = route($module.'.report.dokumen', ['kompilasi' => $f['compilation']->id, 'filename' => basename($f['copy']['path'])]);

    return $f;
}

test('BUG-001 sesi aktif membuka salinan privat tanpa public storage dan tanpa hak reviu', function (string $module) {
    $f = bugReportFixture($module);
    $viewer = workflowUser('viewer_'.$module);
    $viewer->komite()->detach();
    $original = Storage::disk('public')->get($f['source']->dokumen);
    expect($f['copy']['disk'])->toBe('local');
    Storage::disk('public')->assertMissing($f['copy']['path']);
    Storage::disk('public')->delete($f['source']->dokumen);
    $before = $f['compilation']->fresh()->getRawOriginal();

    $this->actingAs($viewer)->get($f['url'])->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertContent($original);
    expect($this->get($f['url'])->headers->get('Content-Disposition'))->toStartWith('inline;');
    expect($this->get($f['url'])->headers->get('Cache-Control'))->toContain('no-store', 'private');
    expect($f['compilation']->fresh()->getRawOriginal())->toBe($before);
    $this->get(route($module.'.reviu.dokumen', $f['review']))->assertForbidden();
})->with(['snp', 'djsn']);

test('BUG-001 belum login kembali ke dokumen setelah login password atau Google', function (string $module, string $method) {
    $f = bugReportFixture($module);
    $f['actor']->update(['email' => 'penguji@bpjsketenagakerjaan.go.id', 'password' => Hash::make('password-uji')]);
    Auth::logout();
    $this->get($f['url'])->assertRedirect(route('login'))->assertSessionHas('url.intended', $f['url']);

    if ($method === 'password') {
        $response = $this->post(route('login'), ['email' => $f['actor']->email, 'password' => 'password-uji']);
    } else {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn((new GoogleUser)->map([
            'id' => 'google-penguji', 'email' => $f['actor']->email, 'name' => 'Penguji', 'avatar' => null,
        ]));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
        $response = $this->get('/auth/google/callback');
    }
    $response->assertRedirect($f['url']);
    $this->assertAuthenticatedAs($f['actor']->fresh());
    $this->get($f['url'])->assertOk();
    Auth::logout();
    $this->get($f['url'])->assertRedirect(route('login'));
})->with(['snp', 'djsn'])->with(['password', 'google']);

test('BUG-001 akun tanpa hak laporan tidak dapat membuka tautan atau memakai akses modul lain', function (string $module) {
    $f = bugReportFixture($module);
    foreach ([User::factory()->create(['status' => 'active']), workflowUser('viewer_'.($module === 'snp' ? 'djsn' : 'snp'))] as $outsider) {
        $this->actingAs($outsider)->get($f['url'])->assertForbidden();
        $this->get($f['url'].'?module='.($module === 'snp' ? 'djsn' : 'snp'))->assertForbidden();
    }
})->with(['snp', 'djsn']);

test('BUG-001 salinan public lama tetap dapat dibaca tanpa mengubah metadata', function (string $module) {
    $f = bugReportFixture($module);
    $material = $f['compilation']->bahan()->firstOrFail();
    $copy = $material->salinan;
    $bytes = Storage::disk('local')->get($copy['dokumen']['path']);
    Storage::disk('public')->put($copy['dokumen']['path'], $bytes);
    Storage::disk('local')->delete($copy['dokumen']['path']);
    $copy['dokumen']['disk'] = 'public';
    DB::connection('mysql_'.$module)->table('tb_kompilasi_bahan')->where('id', $material->id)->update(['salinan' => json_encode($copy)]);
    $before = $f['compilation']->bahan()->firstOrFail()->getRawOriginal();
    $this->actingAs($f['actor'])->get($f['url'])->assertOk()->assertContent($bytes);
    expect($f['compilation']->bahan()->firstOrFail()->getRawOriginal())->toBe($before);
})->with(['snp', 'djsn']);

test('BUG-001 berkas hilang atau berubah diberi keterangan tanpa mengambil versi sumber terbaru', function (string $module, bool $missing) {
    $f = bugReportFixture($module);
    if ($missing) {
        Storage::disk('local')->delete($f['copy']['path']);
    } else {
        Storage::disk('local')->put($f['copy']['path'], 'BUKAN SALINAN RESMI');
    }
    Storage::disk('public')->assertExists($f['source']->dokumen);
    $this->actingAs($f['actor'])->get($f['url'])->assertStatus($missing ? 404 : 409)
        ->assertSee('Dokumen belum tersedia')->assertDontSee('BUKAN SALINAN RESMI');
})->with(['snp', 'djsn'])->with([true, false]);

test('BUG-001 tautan terikat kompilasi dan menolak path bebas atau salinan belum terverifikasi', function (string $module) {
    $f = bugReportFixture($module);
    $other = bugReportFixture($module);
    $wrong = route($module.'.report.dokumen', ['kompilasi' => $other['compilation']->id, 'filename' => basename($f['copy']['path'])]);
    $this->actingAs($f['actor'])->get($wrong)->assertNotFound();
    $this->get($other['url'].'?path='.$f['copy']['path'])->assertOk()
        ->assertContent(Storage::disk('local')->get($other['copy']['path']));
    $this->get(route($module.'.report.dokumen', ['kompilasi' => $f['compilation']->id, 'filename' => '..%2F.env']))->assertNotFound();
    DB::connection('mysql_'.$module)->table('tb_kompilasi')->where('id', $f['compilation']->id)->update(['status_bahan' => 'perlu_rekonsiliasi']);
    $this->get($f['url'])->assertNotFound();
})->with(['snp', 'djsn']);

test('BUG-001 PDF biasa custom dan lampiran reviu memakai tautan login yang dapat dibuka', function (string $module) {
    $f = bugReportFixture($module);
    $this->actingAs($f['actor'])->patch(route($module.'.reviu.update', $f['review']), [
        'status' => 'belum_ditanggapi', 'hasil_review' => 'Catatan pemeriksaan',
        'dokumen' => UploadedFile::fake()->createWithContent('reviu.pdf', bugReportPdf('DOKUMEN REVIU')),
        ...($module === 'snp' ? ['dokumen_memo' => UploadedFile::fake()->createWithContent('memo.pdf', bugReportPdf('MEMO'))] : []),
    ])->assertRedirect()->assertSessionHasNoErrors();

    foreach (['cetak', 'cetak-custom'] as $kind) {
        $html = $this->post(route($module.'.report.'.$kind), [
            'record_ids' => [$f['butir']->record->id], 'butir_ids' => [$f['butir']->id], 'fields' => ['dokumen'],
        ])->assertOk()->viewData('reportHtml');
        expect($html)->not->toContain('/storage/dokumen/');
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $links = (new DOMXPath($dom))->query('//a[contains(@href, "/report/kompilasi/")]');
        $urls = array_unique(array_map(fn ($link): string => $link->getAttribute('href'), iterator_to_array($links)));
        expect($urls)->toHaveCount($module === 'snp' ? 4 : 3);
        $viewer = workflowUser('viewer_'.$module);
        foreach ($urls as $url) {
            $this->actingAs($viewer)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $pdf = new Dompdf;
        $pdf->loadHtml($html);
        $pdf->render();
        $output = $pdf->output(['compress' => 0]);
        expect($output)->toContain('/URI', '/report/kompilasi/')->not->toContain('/storage/dokumen/');
        $this->actingAs($f['actor']);
    }
    $this->get(route($module.'.reviu.dokumen', $f['review']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
})->with(['snp', 'djsn']);

test('BUG-001 salinan dua putaran dan Close tetap dapat dibuka sesudah sumber diganti', function (string $module, string $decision) {
    $f = bugReportFixture($module);
    $oldBytes = Storage::disk('local')->get($f['copy']['path']);
    $this->actingAs($f['actor'])->patch(route($module.'.tanggapan.update', $f['source']), [
        'isi' => 'Versi baru', 'deliverables' => 'Hasil baru', 'dokumen' => UploadedFile::fake()->createWithContent('baru.pdf', bugReportPdf('SUMBER BARU')),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $review = $f['review'];
    for ($number = 1; $number <= 2; $number++) {
        $this->patch(route($module.'.reviu.update', $review), [
            'hasil_review' => 'Perbaiki', 'status' => 'tindak_lanjut_belum_sesuai',
            'jatuh_tempo_tl' => app(SuratTanggalService::class)->suggestedDate(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        bugReportSubmission($f, 'TL '.$number);
        $review = bugReportCompilation($f, 'TL '.$number);
    }
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Keputusan akhir', 'status' => $decision])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->get($f['url'])->assertOk()->assertContent($oldBytes);
    $data = app(KompilasiBahanReader::class)->read($review->kompilasiPutaran);
    $this->get($data['materials']->sole()['documents']->sole()['url'])->assertOk();
    expect($f['butir']->record->fresh()->status)->toBe($decision);
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);

test('BUG-001 dokumen Word diunduh sebagai lampiran dan tidak ditampilkan sebagai HTML', function (string $module) {
    $f = bugReportFixture($module);
    $compilation = $f['compilation'];
    $context = $compilation->salinan_konteks;
    $path = 'dokumen/salinan-kompilasi/hasil.docx';
    $bytes = 'ISI DOKUMEN WORD';
    Storage::disk('local')->put($path, $bytes);
    $context['kompilasi']['dokumen'] = ['disk' => 'local', 'path' => $path, 'sha256' => hash('sha256', $bytes)];
    DB::connection('mysql_'.$module)->table('tb_kompilasi')->where('id', $compilation->id)->update(['salinan_konteks' => json_encode($context)]);
    $url = route($module.'.report.dokumen', ['kompilasi' => $compilation->id, 'filename' => 'hasil.docx']);
    $response = $this->get($url)->assertOk()->assertContent($bytes)
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
})->with(['snp', 'djsn']);
