<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CustomField Model 
 *
 * @property int $id
 * @property string $name
 * @property int $space_id
 * @property string $type
 * @property array|null $options
 * @property bool $is_required
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'space_id',
        'type',
        'options',
        'is_required',
        'position',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'position' => 'integer',
    ];

    protected $attributes = [
        'type' => 'text',
        'is_required' => false,
        'position' => 0,
    ];

    /**
     * Field types.
     */
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_DATE = 'date';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_URL = 'url';
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_EMOJI = 'emoji';
    public const TYPE_PEOPLE = 'people';
    public const TYPE_FILES = 'files';
    public const TYPE_FORMULA = 'formula';
    public const TYPE_RELATIONSHIP = 'relationship';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_NUMBER,
        self::TYPE_DROPDOWN,
        self::TYPE_DATE,
        self::TYPE_CHECKBOX,
        self::TYPE_EMAIL,
        self::TYPE_PHONE,
        self::TYPE_URL,
        self::TYPE_CURRENCY,
        self::TYPE_EMOJI,
        self::TYPE_PEOPLE,
        self::TYPE_FILES,
        self::TYPE_FORMULA,
        self::TYPE_RELATIONSHIP,
    ];

    /**
     * Get the space that owns the custom field.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get all values for this custom field.
     */
    public function values(): HasMany
    {
        return $this->hasMany(TaskCustomFieldValue::class);
    }

    /**
     * Get dropdown options (if applicable).
     */
    public function getDropdownOptionsAttribute(): array
    {
        if ($this->type !== self::TYPE_DROPDOWN) {
            return [];
        }
        return $this->options['options'] ?? [];
    }

    /**
     * Check if field requires options.
     */
    public function requiresOptions(): bool
    {
        return in_array($this->type, [self::TYPE_DROPDOWN, self::TYPE_FORMULA, self::TYPE_RELATIONSHIP]);
    }
}
