<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Centralizes secure handling of lawyer-onboarding file uploads
 * (profile photo, CNIC image, face image) so no controller ever
 * trusts a client-supplied filename or extension.
 *
 * - The stored filename is always a fresh UUID; the original
 *   filename is discarded entirely.
 * - The extension is derived from `UploadedFile::extension()`, which
 *   is guessed from the file's actual detected MIME type, not from
 *   whatever extension the client's filename happened to have.
 * - Only a path is ever returned/stored -- never binary content in
 *   the database, and nothing here executes the uploaded file.
 * - `$disk` controls where the file lives: 'public' for content that
 *   is safe to expose (profile photos), 'local' (the default,
 *   private disk) for sensitive verification documents that must
 *   only be reachable through an authorized controller route.
 */
class LawyerFileUploadService
{
    public function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $extension = $file->extension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = Str::uuid()->toString().'.'.$extension;

        // storeAs() with an explicit directory + generated filename
        // never touches the client-supplied name and Laravel's local
        // driver normalizes/rejects path-traversal segments in $directory,
        // so a crafted directory value cannot escape the disk root.
        return $file->storeAs($directory, $filename, $disk);
    }

    /**
     * Best-effort cleanup used when a later step in the same request
     * fails (e.g. the database transaction rolls back) so an upload
     * never becomes an orphaned file with no owning record.
     */
    public function delete(?string $path, string $disk = 'local'): void
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
