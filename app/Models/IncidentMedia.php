<?php

namespace App\Models;

use Database\Factories\IncidentMediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentMedia extends Model
{
    /** @use HasFactory<IncidentMediaFactory> */
    use HasFactory;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_DOCUMENT = 'document';

    public const TYPES = [
        self::TYPE_IMAGE,
        self::TYPE_VIDEO,
        self::TYPE_DOCUMENT,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'incident_id',
        'file_path',
        'original_name',
        'file_type',
        'mime_type',
        'size',
        'sha256_hash',
        'uploaded_by',
        'is_active',
        'deactivated_at',
        'deactivated_by',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * @param  Builder<IncidentMedia>  $query
     * @return Builder<IncidentMedia>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<IncidentMedia>  $query
     * @return Builder<IncidentMedia>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }
}
