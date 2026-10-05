<?php

namespace App\Http\Controllers\Snp;

use App\Http\Controllers\Controller;
use App\Models\SnpRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SnpButirImageController extends Controller
{
    public function store(Request $request, SnpRecord $record): JsonResponse
    {
        abort_unless($request->user()?->canCreateSnpPerekaman(), 403);
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $file = $validated['image'];
        $extension = $file->getMimeType() === 'image/png' ? 'png' : 'jpg';
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs('snp-images/'.$record->id, $filename, 'local');
        abort_unless($path, 500, 'Gambar gagal disimpan. Silakan coba lagi.');

        return response()->json(['url' => route('snp.butir-images.show', [$record, $filename], false)], 201);
    }

    public function show(Request $request, SnpRecord $record, string $filename): StreamedResponse
    {
        abort_unless($request->user()?->canAccessSnpPerekaman(), 403);
        abort_unless(preg_match('/^[a-f0-9-]{36}\.(png|jpg)$/D', $filename), 404);
        $path = 'snp-images/'.$record->id.'/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => str_ends_with($filename, '.png') ? 'image/png' : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
