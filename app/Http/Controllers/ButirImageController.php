<?php

namespace App\Http\Controllers;

use App\Models\DjsnRecord;
use App\Models\EksternalRecord;
use App\Models\RagabRecord;
use App\Models\RawasRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ButirImageController extends Controller
{
    public function store(Request $request, string $record, string $module): JsonResponse
    {
        $permission = 'canCreate'.$this->moduleName($module).'Perekaman';
        abort_unless($request->user()?->{$permission}(), 403);
        $recordModel = $this->record($module, $record);
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $file = $validated['image'];
        $filename = Str::uuid().($file->getMimeType() === 'image/png' ? '.png' : '.jpg');
        $path = $file->storeAs($module.'-images/'.$recordModel->id, $filename, 'local');
        abort_unless($path, 500, 'Gambar gagal disimpan. Silakan coba lagi.');

        return response()->json(['url' => route($module.'.butir-images.show', ['record' => $recordModel->id, 'filename' => $filename], false)], 201);
    }

    public function show(Request $request, string $record, string $filename, string $module): StreamedResponse
    {
        $permission = 'canAccess'.$this->moduleName($module).'Perekaman';
        abort_unless($request->user()?->{$permission}(), 403);
        $recordModel = $this->record($module, $record);
        abort_unless(preg_match('/^[a-f0-9-]{36}\.(png|jpg)$/D', $filename), 404);
        $path = $module.'-images/'.$recordModel->id.'/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => str_ends_with($filename, '.png') ? 'image/png' : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function moduleName(string $module): string
    {
        return match ($module) {
            'ragab' => 'Ragab', 'rawas' => 'Rawas', 'djsn' => 'Djsn', 'eksternal' => 'Eksternal',
            default => abort(404),
        };
    }

    private function record(string $module, string $recordId): Model
    {
        $model = match ($module) {
            'ragab' => RagabRecord::class, 'rawas' => RawasRecord::class,
            'djsn' => DjsnRecord::class, 'eksternal' => EksternalRecord::class,
            default => abort(404),
        };

        return $model::findOrFail($recordId);
    }
}
