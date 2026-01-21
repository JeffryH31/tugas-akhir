<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TaskCustomFieldValue Model
 *
 * @property int $id
 * @property int $task_id
 * @property int $custom_field_id
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TaskCustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'custom_field_id',
        'value',
    ];

    /**
     * Get the task that owns the value.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the custom field definition.
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }

    /**
     * Get the typed value based on custom field type.
     */
    public function getTypedValueAttribute()
    {
        $type = $this->customField->type;

        return match ($type) {
            CustomField::TYPE_NUMBER, CustomField::TYPE_CURRENCY => (float) $this->value,
            CustomField::TYPE_CHECKBOX => (bool) $this->value,
            CustomField::TYPE_DATE => $this->value ? \Carbon\Carbon::parse($this->value) : null,
            CustomField::TYPE_PEOPLE, CustomField::TYPE_FILES => json_decode($this->value, true) ?? [],
            default => $this->value,
        };
    }
}
