<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the public disk. Centralising it means uploads always get
 * a random, extension-checked filename and old files are cleaned up on replace,
 * rather than each controller reinventing both.
 */
class ImageStorage
{
    public function __construct(private readonly string $disk = 'public') {}

    /** Stores a new upload and returns the relative path. */
    public function store(UploadedFile $file, string $directory): string
    {
        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());

        return $file->storeAs($directory, $name, ['disk' => $this->disk]);
    }

    /**
     * Stores a replacement and deletes whatever the field pointed at before.
     */
    public function replace(UploadedFile $file, string $directory, ?string $previousPath): string
    {
        $path = $this->store($file, $directory);

        $this->delete($previousPath);

        return $path;
    }

    /**
     * Turns a stored path into something a browser can fetch.
     *
     * Three kinds of value end up in an image column, and each resolves
     * differently — getting this wrong is how a seeded demo asset ends up
     * pointing at the upload disk, where it does not exist:
     *
     *   https://…            an absolute URL, used as-is
     *   img/location_1.jpg   a template asset in the frontend's public folder
     *   uuid.jpg             an actual upload on the public disk
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'img/')) {
            return '/'.$path;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Deletes a stored file. Seeded demo assets live under `img/` in the
     * frontend's public folder, not on this disk, so they are left alone.
     */
    public function delete(?string $path): void
    {
        if (! $path || str_starts_with($path, 'img/') || str_starts_with($path, 'http')) {
            return;
        }

        Storage::disk($this->disk)->delete($path);
    }
}
