<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeIncidentJob;
use App\Models\AIAnalysis;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\Vehicle;
use App\Services\AIAnalysisReuseService;
use App\Services\MediaFingerprintService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('dashcam:demo-analyze {video : Sample video filename in storage/app/demo-videos} {--driver-id= : Existing active driver id} {--vehicle-id= : Optional active vehicle id} {--type=unsafe_driving : Incident type}')]
#[Description('Create a demo incident from a sample dashcam video and queue AI analysis')]
class AnalyzeDemoDashcamCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MediaFingerprintService $mediaFingerprintService, AIAnalysisReuseService $aiAnalysisReuseService): int
    {
        if (! (bool) config('services.dashcam.demo_mode', true)) {
            $this->error('AI demo mode is disabled.');

            return self::FAILURE;
        }

        $type = (string) $this->option('type');

        if (! in_array($type, Incident::TYPES, true)) {
            $this->error('Invalid incident type.');

            return self::FAILURE;
        }

        $driver = $this->driver();

        if (! $driver instanceof Driver) {
            $this->error('No active driver profile was found. Create or select a driver first.');

            return self::FAILURE;
        }

        if ($this->option('vehicle-id') !== null && ! Vehicle::query()->active()->whereKey($this->option('vehicle-id'))->exists()) {
            $this->error('Selected vehicle does not exist or is inactive.');

            return self::FAILURE;
        }

        $sourcePath = $this->sourcePath((string) $this->argument('video'));
        $hash = $mediaFingerprintService->hashFile($sourcePath);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'mp4';
        $storedPath = 'incident-media/demo-'.Str::uuid().'.'.$extension;

        if (! Storage::disk('public')->put($storedPath, File::get($sourcePath))) {
            $this->error('Unable to copy demo dashcam video into incident media storage.');

            return self::FAILURE;
        }

        $aiAnalysis = null;
        $incident = DB::transaction(function () use ($aiAnalysisReuseService, $driver, $hash, $mediaFingerprintService, &$aiAnalysis, $sourcePath, $storedPath, $type): Incident {
            $incident = Incident::query()->create([
                'driver_id' => $driver->id,
                'vehicle_id' => $this->option('vehicle-id') ?: null,
                'type' => $type,
                'description' => 'Demo dashcam video analysis: '.basename($sourcePath),
                'status' => Incident::STATUS_PENDING,
                'reported_by' => $driver->user_id,
                'is_active' => true,
            ]);

            $incident->media()->create([
                'file_path' => $storedPath,
                'original_name' => basename($sourcePath),
                'file_type' => IncidentMedia::TYPE_VIDEO,
                'mime_type' => 'video/mp4',
                'size' => filesize($sourcePath) ?: 0,
                'sha256_hash' => $hash,
                'uploaded_by' => $driver->user_id,
                'is_active' => true,
            ]);

            $fingerprint = $mediaFingerprintService->fingerprintFromHashes([$hash]);
            $aiAnalysis = $incident->aiAnalyses()->create($aiAnalysisReuseService->attributesFor($fingerprint));

            return $incident;
        });

        if ($aiAnalysis instanceof AIAnalysis && ! $aiAnalysis->isTerminal()) {
            AnalyzeIncidentJob::dispatch($aiAnalysis->id);
            $this->info("Demo incident #{$incident->id} created and queued for dashcam analysis.");

            return self::SUCCESS;
        }

        $this->info("Demo incident #{$incident->id} created with reused AI analysis.");

        return self::SUCCESS;
    }

    private function driver(): ?Driver
    {
        $driverId = $this->option('driver-id');

        return Driver::query()
            ->active()
            ->when($driverId !== null, fn ($query) => $query->whereKey($driverId))
            ->orderBy('id')
            ->first();
    }

    private function sourcePath(string $filename): string
    {
        $directory = realpath((string) config('services.dashcam.demo_video_path', storage_path('app/demo-videos')));
        $path = $directory === false ? false : realpath($directory.DIRECTORY_SEPARATOR.basename($filename));

        if ($directory === false || $path === false || ! str_starts_with($path, $directory)) {
            $this->fail('Demo video was not found in storage/app/demo-videos.');
        }

        return $path;
    }
}
