<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\IncidentMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class IncidentVisualInputBuilder
{
    public function __construct(private VideoFrameExtractor $videoFrameExtractor) {}

    /**
     * @return array{
     *     content_parts: list<array<string, mixed>>,
     *     media: list<array<string, mixed>>
     * }
     */
    public function build(Incident $incident): array
    {
        $incident->loadMissing('media');

        $contentParts = [];
        $metadata = [];

        foreach ($incident->media as $media) {
            if (! $media->isActive()) {
                continue;
            }

            if ($media->file_type === IncidentMedia::TYPE_IMAGE) {
                $contentParts[] = $this->imageInput($media);
                $metadata[] = $this->mediaMetadata($media, 1);

                continue;
            }

            if ($media->file_type === IncidentMedia::TYPE_VIDEO) {
                $frameInputs = $this->videoFrameInputs($media);

                foreach ($frameInputs as $frameInput) {
                    $contentParts[] = $frameInput;
                }

                $metadata[] = $this->mediaMetadata($media, count($frameInputs));
            }
        }

        if ($contentParts === []) {
            throw new RuntimeException('No active image or video media is available for OpenAI visual analysis.');
        }

        return [
            'content_parts' => $contentParts,
            'media' => $metadata,
        ];
    }

    public function hasVisualMedia(Incident $incident): bool
    {
        $incident->loadMissing('media');

        return $incident->media
            ->contains(fn (IncidentMedia $media): bool => $media->isActive()
                && in_array($media->file_type, [IncidentMedia::TYPE_IMAGE, IncidentMedia::TYPE_VIDEO], true)
            );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function videoFrameInputs(IncidentMedia $media): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($media->file_path)) {
            throw new RuntimeException('The incident video file could not be found.');
        }

        $extraction = $this->videoFrameExtractor->extract(
            $media,
            $disk->path($media->file_path),
            (int) config('services.openai.frame_count', 4)
        );

        try {
            return collect($extraction['frames'])
                ->map(fn (string $framePath): array => $this->base64ImageInput($framePath, 'image/jpeg'))
                ->values()
                ->all();
        } finally {
            File::deleteDirectory($extraction['directory']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function imageInput(IncidentMedia $media): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($media->file_path)) {
            throw new RuntimeException('The incident image file could not be found.');
        }

        return $this->base64ImageInput($disk->path($media->file_path), $media->mime_type ?: 'image/jpeg');
    }

    /**
     * @return array<string, mixed>
     */
    private function base64ImageInput(string $path, string $mimeType): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The incident image input could not be read.');
        }

        return [
            'type' => 'input_image',
            'image_url' => 'data:'.$mimeType.';base64,'.base64_encode($contents),
            'detail' => (string) config('services.openai.image_detail', 'high'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaMetadata(IncidentMedia $media, int $inputCount): array
    {
        return [
            'id' => $media->id,
            'file_type' => $media->file_type,
            'mime_type' => $media->mime_type,
            'original_name' => $media->original_name,
            'size' => $media->size,
            'visual_input_count' => $inputCount,
        ];
    }
}
