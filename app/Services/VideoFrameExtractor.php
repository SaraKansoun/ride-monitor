<?php

namespace App\Services;

use App\Models\IncidentMedia;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class VideoFrameExtractor
{
    /**
     * @return array{directory: string, frames: list<string>}
     */
    public function extract(IncidentMedia $media, string $sourcePath, int $maxFrames): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('The incident video file could not be found.');
        }

        $maxFrames = max(1, $maxFrames);
        $directory = storage_path('app/ai-analysis/frames/'.$media->id.'-'.Str::uuid());

        File::ensureDirectoryExists($directory);

        $binary = (string) config('services.openai.ffmpeg_binary', 'ffmpeg');
        $outputPattern = $directory.DIRECTORY_SEPARATOR.'frame-%03d.jpg';
        $process = new Process([
            $binary,
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-i',
            $sourcePath,
            '-vf',
            'fps=1,scale=1280:-2',
            '-frames:v',
            (string) $maxFrames,
            $outputPattern,
        ]);

        $process->setTimeout((int) config('services.openai.ffmpeg_timeout', 60));

        try {
            $process->run();
        } catch (Throwable $exception) {
            File::deleteDirectory($directory);

            throw new RuntimeException('Unable to start FFmpeg. Confirm FFMPEG_BINARY is configured.', 0, $exception);
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'could not extract video frames.';

            File::deleteDirectory($directory);

            throw new RuntimeException('FFmpeg frame extraction failed: '.$error);
        }

        $frames = collect(File::files($directory))
            ->sortBy(fn ($file) => $file->getFilename())
            ->take($maxFrames)
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();

        if ($frames === []) {
            File::deleteDirectory($directory);

            throw new RuntimeException('FFmpeg did not produce any usable video frames.');
        }

        return [
            'directory' => $directory,
            'frames' => $frames,
        ];
    }
}
