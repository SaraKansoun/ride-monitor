<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAIIncidentAnalysisClient
{
    /**
     * @param  list<array<string, mixed>>  $visualInputs
     * @param  list<array<string, mixed>>  $mediaMetadata
     * @return array{
     *     summary: string,
     *     detected_events: string,
     *     confidence_score: float,
     *     recommendation: string,
     *     raw_response: array<string, mixed>
     * }
     */
    public function analyze(Incident $incident, array $visualInputs, array $mediaMetadata): array
    {
        $model = (string) config('services.openai.model', 'gpt-5.4-mini');
        $response = $this->http()
            ->post('responses', [
                'model' => $model,
                'instructions' => $this->instructions(),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => array_merge([
                            [
                                'type' => 'input_text',
                                'text' => $this->incidentPrompt($incident, $mediaMetadata),
                            ],
                        ], $visualInputs),
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'incident_safety_analysis',
                        'strict' => true,
                        'schema' => $this->responseSchema(),
                    ],
                ],
                'store' => false,
                'metadata' => [
                    'incident_id' => (string) $incident->id,
                ],
            ])
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException('OpenAI returned an invalid response.');
        }

        $output = $this->parseOutput($response);
        $summary = $this->stringValue($output, 'summary');
        $detectedEvents = $this->stringValue($output, 'detected_events');
        $recommendation = $this->stringValue($output, 'recommendation');

        $this->ensureAdvisoryLanguage($summary, $detectedEvents, $recommendation);

        return [
            'summary' => $summary,
            'detected_events' => $detectedEvents,
            'confidence_score' => $this->confidenceScore($output['confidence_score'] ?? null),
            'recommendation' => $recommendation,
            'raw_response' => [
                'source' => 'openai_responses',
                'response_id' => $response['id'] ?? null,
                'model' => $response['model'] ?? $model,
                'usage' => $response['usage'] ?? null,
                'media' => $mediaMetadata,
                'output' => $output,
            ],
        ];
    }

    private function http(): PendingRequest
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/')
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('services.openai.timeout', 60))
            ->retry(2, 1000);
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
You assist a fleet safety monitor by describing observable visual safety signals in incident media.
Use cautious, non-final language such as "appears to", "possible", "may indicate", and "manual review recommended".
Do not decide fault, legal responsibility, liability, guilt, or whether a report is proven.
Never say "the driver is legally guilty", "fault is confirmed", "this proves", or equivalent final-fault language.
Return only observations that can support a human monitor's review.
PROMPT;
    }

    /**
     * @param  list<array<string, mixed>>  $mediaMetadata
     */
    private function incidentPrompt(Incident $incident, array $mediaMetadata): string
    {
        $driver = $incident->driver instanceof Driver ? $incident->driver : null;
        $driverUser = $driver?->user instanceof User ? $driver->user : null;
        $driverStatus = $driver instanceof Driver ? $driver->status : 'unknown';
        $vehicle = $incident->vehicle instanceof Vehicle ? $incident->vehicle : null;

        return sprintf(
            "Review the attached visual inputs for advisory safety observations only.\nIncident type: %s\nIncident status: %s\nDescription: %s\nDriver profile status: %s\nVehicle: %s\nVisual media metadata: %s",
            str_replace('_', ' ', $incident->type),
            str_replace('_', ' ', $incident->status),
            $incident->description,
            $driverStatus,
            $vehicle instanceof Vehicle ? trim(implode(' ', array_filter([$vehicle->plate_number, $vehicle->model, (string) $vehicle->year]))) : 'not selected',
            json_encode([
                'driver_user_id' => $driverUser?->id,
                'media' => $mediaMetadata,
            ], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'description' => 'A concise advisory-only safety summary using cautious language.',
                ],
                'detected_events' => [
                    'type' => 'string',
                    'description' => 'Comma-separated possible visual observations or "No clear visual safety events observed".',
                ],
                'confidence_score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 1,
                ],
                'recommendation' => [
                    'type' => 'string',
                    'description' => 'A cautious recommendation that always preserves final human monitor review.',
                ],
            ],
            'required' => ['summary', 'detected_events', 'confidence_score', 'recommendation'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function parseOutput(array $response): array
    {
        foreach (($response['output'] ?? []) as $output) {
            if (! is_array($output) || ($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($output['content'] ?? []) as $content) {
                if (! is_array($content)) {
                    continue;
                }

                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI refused to analyze this incident media.');
                }

                if (($content['type'] ?? null) !== 'output_text' || ! is_string($content['text'] ?? null)) {
                    continue;
                }

                $decoded = json_decode($content['text'], true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new RuntimeException('OpenAI did not return a usable structured analysis.');
    }

    /**
     * @param  array<string, mixed>  $output
     */
    private function stringValue(array $output, string $key): string
    {
        $value = $output[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("OpenAI response is missing {$key}.");
        }

        return Str::of($value)->squish()->toString();
    }

    private function confidenceScore(mixed $value): float
    {
        if (! is_numeric($value)) {
            throw new RuntimeException('OpenAI response is missing confidence_score.');
        }

        return round(max(0, min(1, (float) $value)), 2);
    }

    private function ensureAdvisoryLanguage(string ...$values): void
    {
        $text = Str::lower(implode(' ', $values));

        foreach (['legally guilty', 'fault is confirmed', 'this proves'] as $forbiddenPhrase) {
            if (Str::contains($text, $forbiddenPhrase)) {
                throw new RuntimeException('OpenAI response used disallowed final-fault language.');
            }
        }
    }
}
