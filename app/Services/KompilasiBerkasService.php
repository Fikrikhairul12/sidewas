<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KompilasiBerkasService
{
    /** @param array<string, array<string, mixed>> $copies */
    public function document(?string $path, array &$copies): ?array
    {
        if (! $path) {
            return null;
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extension = in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'], true) ? $extension : 'bin';

        return $this->copy('public', $path, 'dokumen/salinan-kompilasi/'.Str::uuid().($extension ? '.'.$extension : ''), $copies, 'local');
    }

    /** @param array<string, array<string, mixed>> $copies */
    public function content(?string $value, string $module, int $recordId, array &$copies): array
    {
        $images = [];
        $frozen = $value;
        foreach (app(SnpButirContent::class)->images($value) as $image) {
            if ($image['module'] !== $module || $image['record_id'] !== $recordId) {
                throw ValidationException::withMessages(['hasil_kompilasi' => 'Gambar bahan berasal dari surat lain. Periksa sumber sebelum kompilasi.']);
            }
            $filename = Str::uuid().'.'.pathinfo($image['path'], PATHINFO_EXTENSION);
            $copy = $this->copy('local', $image['path'], $module.'-images/'.$recordId.'/'.$filename, $copies);
            $copy['url'] = route($module.'.butir-images.show', ['record' => $recordId, 'filename' => basename($copy['path'])], false);
            $images[] = $copy + ['source_url' => $image['url'], 'width' => $image['width']];
            $frozen = str_replace($image['url'], $copy['url'], $frozen);
        }

        return ['isi' => $frozen, 'isi_asal' => $value, 'gambar' => $images];
    }

    /** @param array<string, array<string, mixed>> $copies */
    private function copy(string $disk, string $source, string $destination, array &$copies, ?string $destinationDisk = null): array
    {
        $key = $disk.':'.$source;
        if (isset($copies[$key])) {
            return $copies[$key];
        }
        if (str_starts_with($source, '/') || str_contains($source, '\\') || str_contains($source, '..')
            || str_contains($source, ':') || str_contains($source, "\0") || ! Storage::disk($disk)->exists($source)) {
            throw ValidationException::withMessages(['hasil_kompilasi' => 'Berkas bahan tidak tersedia. Lengkapi atau rekonsiliasi sumber sebelum kompilasi.']);
        }
        $bytes = Storage::disk($disk)->get($source);
        if (! is_string($bytes)) {
            throw ValidationException::withMessages(['hasil_kompilasi' => 'Berkas bahan gagal dibaca. Kompilasi belum dikirim.']);
        }
        $destinationDisk ??= $disk;
        $copies[$key] = ['disk' => $destinationDisk, 'path' => $destination, 'source_path' => $source, 'sha256' => hash('sha256', $bytes), 'size' => strlen($bytes)];
        if (! Storage::disk($destinationDisk)->put($destination, $bytes)) {
            throw ValidationException::withMessages(['hasil_kompilasi' => 'Salinan berkas bahan gagal disimpan. Kompilasi belum dikirim.']);
        }

        return $copies[$key];
    }

    /** @param array<string, array<string, mixed>> $copies */
    public function discard(array $copies): void
    {
        foreach ($copies as $copy) {
            Storage::disk($copy['disk'])->delete($copy['path']);
        }
    }
}
