<?php

namespace Theograms\BpmnManager\Elements;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Theograms\BpmnManager\Exceptions\BpmnExecutionException;
use Theograms\BpmnManager\Exceptions\BpmnManagerException;
use Theograms\BpmnManager\Services\BpmnExecutor;
use Theograms\BpmnManager\Services\BpmnManager;

/**
 * @property string|int $id Element id (activity id), same between definitions.
 * @property ?string $uid Unique id (execution id), unique between all processes of all definitions.
 * @property string $tag XML tag without 'bpmn:' prefix
 * @property ?string $name User friendly name
 * @property ?string $parent_id ID of parent BPMN element
 * @property ?State $state
 * @property bool $has_ended True when state is END, false otherwise.
 * @property Collection<string,BpmnElement> $children_elements Keys are the ID of the BPMN elements.
 * @property Collection<string,array> $children Keys are the ID of the BPMN elements.
 * @property Collection<string,array> $incoming Keys the ID of the BPMN elements, values the raw data from the imported xml.
 * @property Collection<string,array> $outgoing Keys the ID of the BPMN elements, values the raw data from the imported xml.
 * @property-read string $debug_name
 *
 * @method advance() Advance this element to the next state if possible. For more info see example implementation in BpmnElement.
 */
abstract class BpmnElement extends Model
{
    use Conditionable;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = []; // all attributes are permitted

    protected $attributes = [
        'state' => null,
        'children' => '[]',
        'incoming' => '[]',
        'outgoing' => '[]',
    ];

    protected $casts = [
        'state' => State::class,
        'children' => 'collection',
        'incoming' => 'collection',
        'outgoing' => 'collection',
    ];

    /**
     * The name of this tag in the BPMN (xml) diagram.
     */
    public const TAG = 'placeholder_tag_in_abstract_BpmnElement';

    /**
     * Indicates if the children are required to have id.
     * When enabled during parsing if child does not have id and exception is thrown.
     */
    public static bool $parse_children = true;

    /**
     * Indicates whether this type of element should be parsed and stored.
     */
    public static bool $processable = true;

    public function getTable()
    {
        return 'virtual_model_'.static::class;
    }

    public function setTagAttribute(string $tag): void
    {
        $this->attributes['tag'] = Str::after($tag, 'bpmn:');
    }

    public function getDebugNameAttribute(): string
    {
        return class_basename(static::class).":{$this->id}".
            (empty($this->name) ? '' : " ({$this->name})").
            (empty($this->uid) ? '' : " #{$this->uid}");
    }

    public function addChild(BpmnElement $child): static
    {
        $this->children = $this->children->put($child->id, $child);

        return $this;
    }

    /**
     * @template T of BpmnElement
     *
     * @param  class-string<T>|string|bool  $require String can be BpmnElement subclass or XML tag. If true an exception will be thrown when element is not found.
     * @return T|null
     */
    public function getChild(string $id, string|bool $require = true): ?BpmnElement
    {
        if (empty($element = $this->children->get($id))) {
            throw_if($require, BpmnManagerException::class, "BPMN element '$id' not found in BPMN Process '{$this->id}'.");

            return null;
        }

        $element = BpmnManager::makeElement($element);

        throw_if(is_string($require) && class_exists($require) && ! $element instanceof $require,
            BpmnManagerException::class,
            "BPMN element '{$element->debug_name}' is not an instance of '$require'."
        );
        throw_if(is_string($require) && ! class_exists($require) && strtoupper($element->tag) != strtoupper($require),
            BpmnManagerException::class,
            "BPMN element '{$element->debug_name}' does not have tag '$require'."
        );

        return $element;
    }

    /**
     * @return Collection<BpmnElement>
     */
    public function getChildrenElementsAttribute(): Collection
    {
        return $this->children->map(fn ($v) => BpmnManager::makeElement($v));
    }

    public function hasState(...$states): bool
    {
        foreach ($states as $state) {
            if ($this->state == $state || $this->state?->value == $state) {
                return true;
            }
        }

        return false;
    }

    public function getHasEndedAttribute(): bool
    {
        return $this->state == State::END;
    }

    protected function changeState(?State $new_state): void
    {
        if ($this->state == $new_state) {
            return;
        }

        // Fire the event only when under evaluation context.
        // We will update the `state` property after calling the listeners so that if a listener crashes the element
        // will remain in the same state.
        if (app()->bound(BpmnExecutor::class)) {
            app(BpmnExecutor::class)->fireBpmnElementStateChanging($this, $new_state);
        }

        $this->state = $new_state;
    }

    public function hasTag(...$tags): bool
    {
        foreach ($tags as $tag) {
            if ($this->tag == $tag) {
                return true;
            }
        }

        return false;
    }

    /**
     * Advance this element to the next state if possible.
     * This is called through {@link BpmnExecutor::executeAndSave()}
     *
     * @param  BpmnElement  $root_element Injects the root level element, usually a `Process`.
     * @return null|array<BpmnElement> Bpmn elements that should be evaluated next or null if execution should stop here.
     */
    //abstract public function advance();

    protected function verifyOutgoingFlow(Flow|array|string|null $flow): void
    {
        throw_if(empty($flow), BpmnExecutionException::class, "{$this->debug_name}: Does not have an outgoing flow.");
        throw_if((is_array($flow) || $flow instanceof Flow) && empty($flow['id']), BpmnExecutionException::class, "{$this->debug_name}: The outgoing flow does not have id.");
    }
}
