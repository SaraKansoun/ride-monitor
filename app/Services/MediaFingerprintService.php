<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaFingerprintService
{
    public function hashUploadedFile(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('Unable to read uploaded media for fingerprinting.');
        }

        return $this->hashFile($path);
    }

    public function hashStoredPublicFile(string $path): string
    {
        $absolutePath = Storage::disk('public')->path($path);

        return $this->hashFile($absolutePath);
    }

    public function hashFile(string $absolutePath): string
    {
        $hash = hash_file('sha256', $absolutePath);

        if (! is_string($hash)) {
            throw new RuntimeException('Unable to hash media file.');
        }

        return $hash;
    }

    public function fingerprintForIncident(Incident $incident): ?string
    {
        $incident->loadMissing('media');

        /** @var Collection<int, string> $hashes */
        $hashes = $incident->media
            ->filter(fn (IncidentMedia $media): bool => $media->isActive()
                && $media->sha256_hash !== null
                && in_array($media->file_type, [IncidentMedia::TYPE_IMAGE, IncidentMedia::TYPE_VIDEO], true))
            ->pluck('sha256_hash')
            ->sort()
            ->values();

        return $this->fingerprintFromHashes($hashes->all());
    }

    /**
     * @param  list<string>  $hashes
     */
    public function fingerprintFromHashes(array $hashes): ?string
    {
        $hashes = collect($hashes)
            ->filter(fn (string $hash): bool => $hash !== '')
            ->sort()
            ->values()
            ->all();

        return $hashes === [] ? null : hash('sha256', implode('|', $hashes));
    }
}
