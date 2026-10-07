<?php

use App\Services\SnpButirContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('shared content sanitizer retains module formatting and rejects foreign or missing images', function (string $module) {
    Storage::fake('local');
    $name = '12345678-1234-1234-1234-123456789abc.png';
    $image = '/'.$module.'/perekaman/1/gambar/'.$name;
    $path = $module.'-images/1/'.$name;
    $file = UploadedFile::fake()->image('image.png');
    Storage::disk('local')->put($path, file_get_contents($file->getPathname()));
    $service = new SnpButirContent;
    $legacy = "Teks lama <literal>\nTetap utuh.";
    expect($service->normalize($legacy, 1, $module))->toBe($legacy);
    $rich = SnpButirContent::PREFIX.'<p style="text-align:center;color:red" onclick="alert(1)"><strong>Tebal</strong><em>Miring</em><u>Garis</u><span style="font-size:24px;position:fixed">Besar</span><script>alert(1)</script><img src="'.$image.'" data-width="50" onerror="alert(1)"></p><ol start="3"><li><p>Poin</p></li></ol>';
    $normalized = $service->normalize($rich, 1, $module);
    expect($normalized)->toContain('text-align:center', 'font-size:24px', '<strong>Tebal</strong>', '<em>Miring</em>', '<u>Garis</u>', '<ol start="3">', 'data-width="50"')
        ->not->toContain('onclick', 'onerror', '<script', 'position', 'color:red')
        ->and($service->normalize($normalized, 1, $module))->toBe($normalized)
        ->and($service->images($normalized)[0]['module'])->toBe($module);
    $foreign = $module === 'ragab' ? 'rawas' : 'ragab';
    Storage::disk('local')->put($foreign.'-images/1/'.$name, file_get_contents($file->getPathname()));
    foreach ([SnpButirContent::PREFIX.'<p><br></p>', str_repeat('a', 500001), str_replace('/perekaman/1/', '/perekaman/2/', $rich), str_replace('/'.$module.'/', '/'.$foreign.'/', $rich), SnpButirContent::PREFIX.str_repeat('<img src="'.$image.'">', 21)] as $invalid) {
        try {
            $service->normalize($invalid, 1, $module);
            $this->fail('Invalid content was accepted.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey(SnpButirContent::contentField($module));
        }
    }
    Storage::disk('local')->delete($path);
    expect(fn () => $service->normalize($rich, 1, $module))->toThrow(ValidationException::class);
})->with(['ragab', 'rawas', 'djsn', 'eksternal']);

test('each module uses the SNP toolbar for formatting image upload editing and enlarged preview', function (string $module) {
    Storage::fake('local');
    $field = SnpButirContent::contentField($module);
    $name = '12345678-1234-1234-1234-123456789abc.png';
    $image = '/'.$module.'/perekaman/1/gambar/'.$name;
    $file = UploadedFile::fake()->image('image.png');
    Storage::disk('local')->put($module.'-images/1/'.$name, file_get_contents($file->getPathname()));
    $initial = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>Isi awal</strong></p><p><img src="'.$image.'" data-width="50"></p>';
    $source = file_get_contents(resource_path('views/layouts/'.$module.'/perekaman.blade.php'));
    preg_match_all('/<x-snp-butir-editor\b.*?\/>/s', $source, $editors);
    expect($editors[0])->toHaveCount(2);
    foreach ($editors[0] as $editor) {
        expect($editor)->toContain('name="'.$field.'"', 'module="'.$module.'"');
    }
    $html = Blade::render(<<<'BLADE'
        <form id="editorForm" x-data="{ selected: 0, drafts: [@js($initial), 'Teks lama <literal>'], record: 1, editRecord: {id: 1}, editorSession: 0, get selectedEditButir() { return { id: this.selected + 1, [@js($field)]: this.drafts[this.selected] }; } }" @submit.prevent>
            <button type="button" id="switch" @click="selected = selected === 0 ? 1 : 0">Ganti butir</button>
            <div @snp-editor-change.stop="drafts[selected] = $event.detail">
        BLADE, compact('initial', 'field'));
    $html .= Blade::render($editors[0][0]);
    $html .= Blade::render(<<<'BLADE'
            </div>
            <button type="submit" id="save">Simpan</button>
            <x-butir-preview id="richPreview" content-expression="drafts[selected]" :expanded="true" />
        </form>
        <x-snp-butir-reader />
        BLADE);
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $process = new Process(['node', base_path('tests/Unit/SnpRichEditor.browser.mjs')], base_path());
    $process->setInput(json_encode(['html' => $html, 'module' => $module, 'field' => $field, 'uploadPath' => Storage::disk('local')->path($module.'-images/1/'.$name), 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']], JSON_THROW_ON_ERROR));
    $process->setTimeout(90);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['ragab', 'rawas', 'djsn', 'eksternal']);
