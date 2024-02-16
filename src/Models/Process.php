<?php

namespace Theograms\BpmnManager\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Theograms\BpmnManager\Elements\BpmnElement;
use Theograms\BpmnManager\Exceptions\BpmnManagerException;
use Theograms\BpmnManager\Services\BpmnManager;

/**
 * @property BpmnElement $state
 */
class Process extends BaseModel
{
    protected $fillable = ['metadata', 'state', 'input'];

    protected $attributes = [
        'metadata' => '{}',
        'input' => '{}',
    ];

    protected $casts = [
        'metadata' => 'array',
        'input' => 'array',
    ];

    public function scopeWhereMetadata(Builder $query, string $key, mixed $value): Builder
    {
        return $query->where("metadata->$key", $value);
    }

    public static function makeFromDefinition(string $xml, array $metadata = []): static
    {
        $process = static::make(['metadata' => $metadata]);
        $process->state = app(BpmnManager::class)->parseXml($xml);

        return $process;
    }

    public function getDebugNameAttribute(): string
    {
        return "#{$this->id}";
    }

    public function setStateAttribute(BpmnElement $element): void
    {
        $this->attributes['state'] = serialize($element);
        Cache::driver('array')->forget(static::class."{$this->id}-unserialized-state");
    }

    public function getStateAttribute(): BpmnElement
    {
        throw_if(empty($this->attributes['state']), BpmnManagerException::class, "Trying to load empty state on Process #{$this->id}.");

        return Cache::driver('array')->rememberForever(static::class."{$this->id}-unserialized-state",
            fn () => unserialize($this->attributes['state'])
        );
    }

    public function setInputFor(string $bpmn_element_key, mixed $data): static
    {
        $input = $this->input ?? [];
        $input[$bpmn_element_key] = $data;
        $this->input = $input;

        return $this;
    }

    /**
     * @param  string|BpmnElement  $bpmn_element BPMN element id or element
     */
    public function getInputFor(string|BpmnElement $bpmn_element): mixed
    {
        return $this->input[$bpmn_element instanceof BpmnElement ? $bpmn_element->id : $bpmn_element] ?? null;
    }

    public function getMetadata($key): mixed
    {
        return data_get($this->metadata, $key);
    }

    public function setMetadata($key, $value): static
    {
        $metadata = $this->metadata;
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        return $this;
    }
}
