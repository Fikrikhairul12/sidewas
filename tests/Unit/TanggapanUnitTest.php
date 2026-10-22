<?php

use App\Enums\SuratStatus;
use App\Exports\DjsnReportExport;
use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SnpReview;
use App\Models\SnpTanggapan;
use App\Services\SuratPutaranService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    Storage::fake('public');
    DB::table('tb_unit_kerja')->where('id', 1)->update(['kode_unit' => 'A', 'nama_unit' => 'Unit A']);
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'direktorat_id' => 1, 'kode_unit' => 'B', 'nama_unit' => 'Unit B']);
});

function assignedUnitPic(SnpButir|DjsnButir $butir, int $unitId, string $jenis = 'pendukung'): int
{
    $module = $butir instanceof SnpButir ? 'snp' : 'djsn';

    return DB::connection('mysql_'.$module)->table('tb_butir_pic')->insertGetId([
        'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => $jenis, 'unit_kerja_id' => $unitId,
    ]);
}

function responsePayload(int $picId, ?int $roundId = null, string $text = 'Jawaban unit'): array
{
    return ['butir_pic_id' => $picId, 'putaran_id' => $roundId, 'tanggapan' => $text, 'deliverables' => 'Hasil unit'];
}

test('one response belongs to a unit even when users and PIC assignments differ', function (string $module) {
    $butir = workflowButir($module);
    $picA = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $otherPicA = assignedUnitPic($butir, 1);
    $picB = assignedUnitPic($butir, 2);
    $userA = workflowUser('pic_'.$module);
    $colleagueA = workflowUser('pic_'.$module);
    $userB = workflowUser('pic_'.$module);
    $userB->unitKerja()->sync([2 => ['status' => 'active']]);
    $route = route($module.'.tanggapan.store', $butir);
    $this->actingAs($userA)->post($route, responsePayload($picA))->assertRedirect()->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $response = $class::firstOrFail();
    expect($response->unit_kerja_id)->toBe(1)->and($response->created_by)->toBe($userA->id);

    foreach ([$picA, $otherPicA] as $pic) {
        $this->actingAs($colleagueA)->post($route, responsePayload($pic))->assertSessionHasErrors('butir_pic_id');
    }
    expect(fn () => $class::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'butir_pic_id' => $otherPicA, 'tanggapan' => 'Ganda lewat model']))->toThrow(LogicException::class);
    $this->get(route($module.'.tanggapan.index'))->assertOk()->assertDontSee('data-beri-tanggapan=', false)
        ->assertViewHas('responseStates', fn ($states): bool => $states[$butir->id]['available_pics']->isEmpty()
            && $states[$butir->id]['detail']['pic_tanggapans'][0]['can_edit'] === true);
    $this->get(route($module.'.tanggapan.index', ['status' => 'sudah']))->assertViewHas('butirs', fn ($rows): bool => $rows->total() === 1);
    $this->post($route, responsePayload($picB))->assertForbidden();

    $this->actingAs($userB)->get(route($module.'.tanggapan.index', ['status' => 'belum']))->assertOk()
        ->assertSee('data-beri-tanggapan="'.$butir->id.'"', false)
        ->assertViewHas('responseStates', fn ($states): bool => $states[$butir->id]['available_pics']->pluck('unit_kerja_id')->all() === [2]);
    $this->post($route, responsePayload($picA))->assertForbidden();
    $this->patch(route($module.'.tanggapan.update', $response), ['tanggapan' => 'Unit B mengubah A', 'deliverables' => 'X'])->assertForbidden();
    $this->post($route, responsePayload($picB))->assertRedirect()->assertSessionHasNoErrors();
    expect($class::count())->toBe(2)->and($class::orderBy('unit_kerja_id')->pluck('unit_kerja_id')->all())->toBe([1, 2]);
    if ($module === 'djsn') {
        expect(DjsnReview::count())->toBe(1)->and(DjsnReview::firstOrFail()->id_tanggapan)->toBe($response->id);
    }

    $this->actingAs($colleagueA)->patch(route($module.'.tanggapan.update', $response), ['tanggapan' => "Revisi <literal> & teks\nBaru", 'deliverables' => 'Revisi'])->assertRedirect()->assertSessionHasNoErrors();
    expect($response->fresh()->created_by)->toBe($userA->id)
        ->and($response->fresh()->updated_by)->toBe($colleagueA->id)
        ->and($response->fresh()->tanggapan)->toBe("Revisi <literal> & teks\nBaru")
        ->and($class::count())->toBe(2);
})->with(['snp', 'djsn']);

test('the same unit can answer different butir and new rounds while stale forms and old edits are rejected', function (string $module) {
    $user = workflowUser('pic_'.$module);
    $this->actingAs($user);
    $butir = workflowButir($module);
    $pic = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $this->post(route($module.'.tanggapan.store', $butir), responsePayload($pic, null, 'Jawaban lama'))->assertSessionHasNoErrors();
    $old = $class::firstOrFail();
    $otherButir = $butir->newQuery()->create(['id_'.$module => $butir->{'id_'.$module}, 'butir_'.$module => 'Butir kedua']);
    $otherPic = assignedUnitPic($otherButir, 1, 'utama');
    $this->post(route($module.'.tanggapan.store', $otherButir), responsePayload($otherPic))->assertSessionHasNoErrors();
    $workflow = app(SuratPutaranService::class);
    $surat = $butir->record->fresh();
    $surat->update(['status' => SuratStatus::TindakLanjutBelumSesuai->value]);
    $round = $workflow->buka($surat, $user->id);

    foreach ([1, 2] as $number) {
        $this->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('data-beri-tanggapan="'.$butir->id.'"', false)
            ->assertViewHas('responseStates', fn ($states): bool => $states[$butir->id]['responses']->isEmpty() && $states[$butir->id]['round_id'] === $round->id);
        $this->post(route($module.'.tanggapan.store', $butir), responsePayload($pic, $old->putaran_id))->assertSessionHasErrors('putaran_id');
        $this->post(route($module.'.tanggapan.store', $butir), responsePayload($pic))->assertSessionHasErrors('putaran_id');
        $this->post(route($module.'.tanggapan.store', $butir), responsePayload($pic, $round->id, 'Jawaban putaran '.$number))->assertSessionHasNoErrors();
        $this->get(route($module.'.tanggapan.index', ['keyword' => $butir->{'id_butir_'.$module}]))->assertOk()
            ->assertViewHas('responseStates', fn ($states): bool => $states[$butir->id]['responses']->count() === 1 && $states[$butir->id]['available_pics']->isEmpty());
        $this->patch(route($module.'.tanggapan.update', $old), ['tanggapan' => 'Menimpa lama', 'deliverables' => 'X'])->assertForbidden();
        expect($surat->fresh()->status)->toBe(SuratStatus::TindakLanjutBelumSesuai->value)->and($old->fresh()->tanggapan)->toBe('Jawaban lama');
        if ($number === 1) {
            $workflow->arsipkan($round, $user->id);
            $round = $workflow->buka($surat->fresh(), $user->id);
        }
    }
    expect($class::where('id_butir_'.$module, $butir->{'id_butir_'.$module})->count())->toBe(3);
    $this->actingAs(workflowUser('super_admin'))->patch(route($module.'.tanggapan.update', $old), ['tanggapan' => 'Menimpa sebagai admin', 'deliverables' => 'X'])->assertForbidden();
})->with(['snp', 'djsn']);

test('reviewed material is immutable including admins and a missing unit can complete a closed round', function (string $module, string $status) {
    $this->actingAs(workflowUser('pic_'.$module));
    $butir = workflowButir($module);
    $picA = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $picB = assignedUnitPic($butir, 2);
    $db = DB::connection('mysql_'.$module);
    $this->post(route($module.'.tanggapan.store', $butir), responsePayload($picA))->assertSessionHasNoErrors();
    $response = $butir->tanggapanUnit()->firstOrFail();
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    $review = $reviewClass::firstOrCreate(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'putaran_id' => $response->putaran_id, 'tahap_review' => 'tanggapan'], ['id_tanggapan' => $response->id, 'komite_id' => 1]);
    $review->update(['hasil_review' => 'Keputusan reviu terkunci']);
    $snapshot = $review->fresh()->getAttributes();
    foreach (['pic_'.$module, 'admin_'.$module, 'super_admin'] as $role) {
        $this->actingAs(workflowUser($role))->patch(route($module.'.tanggapan.update', $response), ['tanggapan' => 'Menimpa hasil reviu', 'deliverables' => 'X'])->assertForbidden();
    }
    $butir->record->update(['status' => $status]);
    $counts = ['review' => $db->table('tb_review')->count(), 'compilation' => $db->table('tb_kompilasi')->count()];
    $userB = workflowUser('pic_'.$module);
    $userB->unitKerja()->sync([2 => ['status' => 'active']]);
    $this->actingAs($userB)->post(route($module.'.tanggapan.store', $butir), responsePayload($picB))->assertSessionHasNoErrors();
    $this->get(route($module.'.tanggapan.index'))->assertOk()->assertDontSee('data-beri-tanggapan=', false)
        ->assertViewHas('responseStates', fn ($states): bool => collect($states[$butir->id]['detail']['pic_tanggapans'])->every(fn ($row): bool => $row['can_edit'] === false));
    expect($butir->record->fresh()->status)->toBe($status)
        ->and($review->fresh()->getAttributes())->toBe($snapshot)
        ->and($db->table('tb_review')->count())->toBe($counts['review'])
        ->and($db->table('tb_kompilasi')->count())->toBe($counts['compilation'])
        ->and($response->fresh()->tanggapan)->toBe('Jawaban unit');
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);

test('viewers cannot respond and an unrelated PIC cannot be submitted', function (string $module) {
    $butir = workflowButir($module);
    $pic = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $this->actingAs(workflowUser('viewer_'.$module));
    $this->get(route($module.'.tanggapan.index'))->assertOk()->assertDontSee('data-beri-tanggapan=', false);
    $this->post(route($module.'.tanggapan.store', $butir), responsePayload($pic))->assertForbidden();
    $this->actingAs(workflowUser('pic_'.$module));
    $foreign = workflowButir($module)->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $this->post(route($module.'.tanggapan.store', $butir), responsePayload($foreign))->assertSessionHasErrors('butir_pic_id');
})->with(['snp', 'djsn']);

test('a legacy response without a known unit remains visible without guessing its owner', function (string $module) {
    $this->actingAs(workflowUser('pic_'.$module));
    $butir = workflowButir($module);
    $db = DB::connection('mysql_'.$module);
    $id = $db->table('tb_tanggapan')->insertGetId(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tanggapan' => 'Riwayat tanpa unit', 'created_by' => 45, 'created_at' => '2025-01-01 10:00:00']);
    $original = (array) $db->table('tb_tanggapan')->find($id);
    $this->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('Unit tidak tercatat (data lama)')->assertSee('Riwayat tanpa unit');
    expect((array) $db->table('tb_tanggapan')->find($id))->toBe($original);
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    expect(fn () => $class::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tanggapan' => 'Baru tanpa unit']))->toThrow(InvalidArgumentException::class);
    $this->patch(route($module.'.tanggapan.update', $class::findOrFail($id)), ['tanggapan' => 'Mengaku pemilik', 'deliverables' => 'X'])->assertForbidden();
})->with(['snp', 'djsn']);

test('duplicate requests and locked edits do not replace or orphan uploaded documents', function (string $module) {
    $this->actingAs(workflowUser('pic_'.$module));
    $butir = workflowButir($module);
    $pic = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $data = responsePayload($pic) + ['dokumen' => UploadedFile::fake()->create('lama.pdf', 1, 'application/pdf')];
    $this->post(route($module.'.tanggapan.store', $butir), $data)->assertSessionHasNoErrors();
    $response = $butir->tanggapanUnit()->firstOrFail();
    $firstFile = $response->dokumen;
    $this->post(route($module.'.tanggapan.store', $butir), responsePayload($pic) + ['dokumen' => UploadedFile::fake()->create('ganda.pdf', 1, 'application/pdf')])->assertSessionHasErrors('butir_pic_id');
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
    $this->patch(route($module.'.tanggapan.update', $response), ['tanggapan' => 'Revisi', 'deliverables' => 'Hasil', 'dokumen' => UploadedFile::fake()->create('baru.pdf', 1, 'application/pdf')])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($firstFile);
    Storage::disk('public')->assertExists($response->fresh()->dokumen);
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
})->with(['snp', 'djsn']);

test('SNP compilation accepts incomplete unit responses', function () {
    $this->actingAs(workflowUser('pic_snp'));
    $butir = workflowButir('snp');
    assignedUnitPic($butir, 2);
    $pic = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $this->post(route('snp.tanggapan.store', $butir), responsePayload($pic))->assertSessionHasNoErrors();
    $this->actingAs(workflowUser('super_admin'))->get(route('snp.kompilasi.index'))->assertOk();
    $this->post(route('snp.kompilasi.store', $butir), ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Kompilasi satu unit'])->assertSessionHasNoErrors();
    expect($butir->tanggapanUnit()->count())->toBe(1)->and(SnpKompilasi::firstOrFail()->status)->toBe('dalam_proses_reviu_dewas');
});

test('DJSN standard custom and Excel reports retain both unit responses', function () {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir('djsn');
    $picA = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $picB = assignedUnitPic($butir, 2);
    foreach ([$picA => 'Jawaban UNIT-A', $picB => 'Jawaban UNIT-B'] as $pic => $text) {
        $this->post(route('djsn.tanggapan.store', $butir), responsePayload($pic, null, $text))->assertSessionHasNoErrors();
    }
    $records = collect([$butir->record->fresh()->load(['butirDjsn.tanggapanUnit.unitKerja', 'butirDjsn.tanggapanUnit.review', 'butirDjsn.butirPics.unitKerja', 'butirDjsn.butirPics.komite', 'butirDjsn.tindakLanjuts.reviews'])]);
    foreach (['pdf', 'pdf-custom', 'excel'] as $format) {
        $selectedFields = $format === 'excel' ? ['id_butir', 'isi_butir', 'tanggapan', 'deliverable', 'hasil_reviu'] : ['surat', 'id_butir', 'isi_butir', 'pic_unit', 'tanggapan_tl', 'komite', 'hasil_reviu'];
        $html = view('layouts.djsn.report.'.$format, ['records' => $records, 'selectedFields' => $selectedFields, 'fieldLabels' => []])->render();
        expect($html)->toContain('Jawaban UNIT-A', 'Jawaban UNIT-B');
        if ($format !== 'excel') {
            $pdf = app(PDF::class)->loadHTML($html)->output();
            expect($pdf)->toStartWith('%PDF-');
        }
    }
    $export = new DjsnReportExport($records, ['id_butir', 'isi_butir', 'tanggapan'], []);
    $bytes = Maatwebsite\Excel\Facades\Excel::raw($export, Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'unit-report-');
    try {
        file_put_contents($path, $bytes);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $values = collect($sheet->toArray())->flatten()->implode(' ');
        expect($values)->toContain('Jawaban UNIT-A', 'Jawaban UNIT-B');
        $sheet->getParent()->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

test('actual response pages navigate Detail and edit or create the correct unit and round in the browser', function (string $module) {
    $butir = workflowButir($module);
    $picA = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $picB = assignedUnitPic($butir, 2);
    $userA = workflowUser('pic_'.$module);
    $this->actingAs($userA)->post(route($module.'.tanggapan.store', $butir), responsePayload($picA, null, 'Jawaban unit A'))->assertSessionHasNoErrors();
    $response = $butir->tanggapanUnit()->firstOrFail();
    $pages = [['mode' => 'answered', 'responseId' => $response->id, 'html' => $this->get(route($module.'.tanggapan.index'))->assertOk()->getContent()]];
    $userB = workflowUser('pic_'.$module);
    $userB->unitKerja()->sync([2 => ['status' => 'active']]);
    $pages[] = ['mode' => 'pending', 'picId' => $picB, 'roundId' => $response->putaran_id, 'html' => $this->actingAs($userB)->get(route($module.'.tanggapan.index'))->assertOk()->getContent()];
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    $review = $reviewClass::firstOrCreate(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'putaran_id' => $response->putaran_id, 'tahap_review' => 'tanggapan'], ['id_tanggapan' => $response->id]);
    $review->update(['hasil_review' => 'Sudah direviu']);
    $pages[] = ['mode' => 'reviewed', 'html' => $this->actingAs($userA)->get(route($module.'.tanggapan.index'))->assertOk()->getContent()];
    $round = app(SuratPutaranService::class)->buka($butir->record->fresh(), $userA->id);
    $pages[] = ['mode' => 'new-round', 'picId' => $picA, 'roundId' => $round->id, 'html' => $this->get(route($module.'.tanggapan.index'))->assertOk()->getContent()];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/TanggapanUnit.browser.mjs')], base_path());
    $process->setInput(json_encode(['module' => $module, 'pages' => $pages, 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']]));
    $process->setTimeout(60)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
