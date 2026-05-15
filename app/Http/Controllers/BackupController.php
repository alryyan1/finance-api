<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $disk  = Storage::disk('local');
        $appName = config('backup.backup.name', config('app.name'));
        $path  = $appName;

        if (! $disk->exists($path)) {
            return response()->json([]);
        }

        $files = collect($disk->files($path))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(function ($file) use ($disk) {
                $size = $disk->size($file);
                return [
                    'name'       => basename($file),
                    'path'       => $file,
                    'size'       => $size,
                    'size_human' => $this->humanSize($size),
                    'date'       => date('Y-m-d H:i:s', $disk->lastModified($file)),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return response()->json($files);
    }

    public function run(): JsonResponse
    {
        Artisan::call('backup:run', ['--only-db' => true]);
        $output = Artisan::output();

        return response()->json([
            'message' => 'تم إنشاء النسخة الاحتياطية بنجاح.',
            'output'  => $output,
        ]);
    }

    public function download(string $filename): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $appName = config('backup.backup.name', config('app.name'));
        $path    = storage_path("app/{$appName}/{$filename}");

        abort_if(! file_exists($path), 404, 'الملف غير موجود.');

        return response()->download($path);
    }

    public function destroy(string $filename): JsonResponse
    {
        $appName = config('backup.backup.name', config('app.name'));
        $path    = "{$appName}/{$filename}";

        abort_if(! Storage::disk('local')->exists($path), 404, 'الملف غير موجود.');

        Storage::disk('local')->delete($path);

        return response()->json(['message' => 'تم حذف النسخة الاحتياطية.']);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) return round($bytes / 1_073_741_824, 2) . ' GB';
        if ($bytes >= 1_048_576)    return round($bytes / 1_048_576, 2)    . ' MB';
        if ($bytes >= 1_024)        return round($bytes / 1_024, 2)        . ' KB';
        return $bytes . ' B';
    }
}
