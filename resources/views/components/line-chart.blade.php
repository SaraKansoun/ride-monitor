@props([
    'copy' => null,
    'empty' => 'No chart data is available yet.',
    'kicker' => 'Analytics',
    'points' => [],
    'title',
])

@php
    $pointCollection = collect($points)
        ->map(fn ($point) => [
            'label' => (string) ($point['label'] ?? ''),
            'value' => (int) ($point['value'] ?? 0),
        ])
        ->values();

    $hasPoints = $pointCollection->isNotEmpty();
    $latestPoint = $pointCollection->last();
    $maxValue = $hasPoints ? (int) $pointCollection->max('value') : 0;
    $minValue = $hasPoints ? (int) $pointCollection->min('value') : 0;
    $range = max(1, $maxValue - $minValue);
    $width = 640;
    $height = 220;
    $paddingX = 36;
    $paddingTop = 24;
    $paddingBottom = 42;
    $plotWidth = $width - ($paddingX * 2);
    $plotHeight = $height - $paddingTop - $paddingBottom;
    $lastIndex = max(1, $pointCollection->count() - 1);
    $svgPoints = $pointCollection
        ->map(function (array $point, int $index) use ($hasPoints, $maxValue, $minValue, $paddingTop, $paddingX, $plotHeight, $plotWidth, $range, $lastIndex): array {
            $x = $paddingX + (($plotWidth / $lastIndex) * $index);
            $y = $maxValue === $minValue
                ? $paddingTop + ($plotHeight / 2)
                : $paddingTop + (($maxValue - $point['value']) / $range * $plotHeight);

            return [
                ...$point,
                'x' => round($x, 2),
                'y' => round($y, 2),
            ];
        });
    $polyline = $svgPoints
        ->map(fn (array $point): string => $point['x'].','.$point['y'])
        ->join(' ');
    $labels = $svgPoints
        ->filter(fn (array $point, int $index): bool => $index === 0 || $index === $svgPoints->count() - 1 || $index === (int) floor(($svgPoints->count() - 1) / 2))
        ->values();
@endphp

<article {{ $attributes->class(['line-chart-card']) }}>
    <div class="line-chart-header">
        <div>
            <p class="app-kicker">{{ $kicker }}</p>
            <h2 class="section-title">{{ $title }}</h2>
            @if ($copy)
                <p class="section-copy">{{ $copy }}</p>
            @endif
        </div>

        @if ($latestPoint)
            <span class="line-chart-value">
                <strong>{{ $latestPoint['value'] }}</strong>
                <small>{{ $latestPoint['label'] }}</small>
            </span>
        @endif
    </div>

    @if ($hasPoints)
        <div class="line-chart-viewport">
            <svg class="line-chart-svg" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}">
                <line class="line-chart-grid" x1="{{ $paddingX }}" y1="{{ $paddingTop }}" x2="{{ $width - $paddingX }}" y2="{{ $paddingTop }}" />
                <line class="line-chart-grid" x1="{{ $paddingX }}" y1="{{ $paddingTop + ($plotHeight / 2) }}" x2="{{ $width - $paddingX }}" y2="{{ $paddingTop + ($plotHeight / 2) }}" />
                <line class="line-chart-axis" x1="{{ $paddingX }}" y1="{{ $height - $paddingBottom }}" x2="{{ $width - $paddingX }}" y2="{{ $height - $paddingBottom }}" />
                <polyline class="line-chart-line" points="{{ $polyline }}" />

                @foreach ($svgPoints as $point)
                    <circle class="line-chart-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" />
                @endforeach

                @foreach ($labels as $label)
                    <text class="line-chart-label" x="{{ $label['x'] }}" y="{{ $height - 14 }}">{{ $label['label'] }}</text>
                @endforeach

                <text class="line-chart-axis-label" x="{{ $paddingX }}" y="16">{{ $maxValue }}</text>
                <text class="line-chart-axis-label" x="{{ $paddingX }}" y="{{ $height - $paddingBottom - 6 }}">{{ $minValue }}</text>
            </svg>
        </div>

        <div class="line-chart-data">
            @foreach ($pointCollection as $point)
                <span>{{ $point['label'] }}: {{ $point['value'] }}</span>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <strong>{{ $empty }}</strong>
            <span>Chart data will appear after matching records are created.</span>
        </div>
    @endif
</article>
