<?php

namespace Theograms\BpmnManager\Services;

use Illuminate\Log\Logger;
use Illuminate\Support\Str;
use Theograms\BpmnManager\Elements\BpmnElement;
use Theograms\BpmnManager\Elements\State;
use Theograms\BpmnManager\Events\BpmnElementStateChangingEvent;
use Theograms\BpmnManager\Exceptions\BpmnExecutionException;
use Theograms\BpmnManager\Models\Process;

class BpmnExecutor
{
    public BpmnElement $current_state;

    protected Logger $logger;

    public static function make(Process $process): static
    {
        return app()->make(static::class, ['process' => $process]);
    }

    public function __construct(public Process $process,
        protected readonly \Illuminate\Foundation\Application $app)
    {
    }

    public function executeAndSave(BpmnElement|string $element = null): void
    {
        try {
            $this->execute($element);
        } finally {
            $this->process->save();
        }
    }

    /**
     * Trigger BPMN to start evaluation of elements.
     */
    public function execute(BpmnElement|string $element = null): void
    {
        $this->current_state ??= $this->process->state;

        $element = empty($element)
            ? $this->current_state
            : $this->current_state->getChild(is_string($element) ? $element : $element->id);

        try {
            $this->setup();
            $this->advance($element);
        } finally {
            $this->process->state = $this->current_state;
            $this->teardown();
        }
    }

    public function fireBpmnElementStateChanging(BpmnElement $element, ?State $new_state): void
    {
        $this->app['events']->dispatch(new BpmnElementStateChangingEvent($this->process, $element, $new_state));
    }

    /**
     * Setup binding for BPMN evaluation.
     */
    protected function setup(): void
    {
        $this->logger = BpmnManager::logger()->withContext(['process_id' => $this->process->id]);

        $this->app->bind(BpmnExecutor::class, fn () => $this);

        $this->logger->debug("Starting BpmnExecutor for process {$this->process->debug_name}", [
            'memory_get_usage(true)' => memory_get_usage(true),
            'memory_get_usage(false)' => memory_get_usage(false),
            'memory_get_peak_usage(true)' => memory_get_peak_usage(true),
            'memory_get_peak_usage(false)' => memory_get_peak_usage(false),
        ]);
    }

    /**
     * Remove bindings after BPMN evaluation.
     */
    protected function teardown(): void
    {
        $this->logger->debug("Finishing BpmnExecutor for process {$this->process->debug_name}", [
            'memory_get_usage(true)' => memory_get_usage(true),
            'memory_get_usage(false)' => memory_get_usage(false),
            'memory_get_peak_usage(true)' => memory_get_peak_usage(true),
            'memory_get_peak_usage(false)' => memory_get_peak_usage(false),
        ]);

        $this->app->bind(BpmnExecutor::class); // Unbind BpmnExecutor after execution.
    }

    /**
     * Attempt to execute this element.
     */
    protected function advance(string|BpmnElement $element): void
    {
        if (is_string($element)) {
            $element = $this->findElementOrFail($element);
        }

        // As the elements getting executed fill in `uid`, which unique between all elements of this and other instances.
        if (empty($element->uid)) {
            $element->uid = $element->id.'-'.Str::uuid()->serialize();
        }

        try {
            $this->logger->debug("Advancing process {$this->process->debug_name}: {$element->debug_name} from state '{$element->state?->value}'");
            $next_elements = $this->app->call([$element, 'advance'], ['process' => $this->process, 'root_element' => $this->current_state, 'executor' => $this]);
            $this->logger->debug("Advanced process {$this->process->debug_name}: {$element->debug_name} to state '{$element->state?->value}'", ['next_elements' => $next_elements]);
        } catch (\Throwable $e) {
            $this->logger->error("Failed advancing process {$this->process->debug_name}: {$element->debug_name} current state '{$element->state?->value}', error: {$e->getMessage()}");
            throw $e;
        } finally {
            // Update the possibly changed element.
            // In this way you can set `state=start` at the beginning of the `advance` and if something fails
            // the last state will be persisted.
            $this->current_state->addChild($element);
        }

        if (empty($next_elements)) {
            return;
        }

        $next_elements = is_array($next_elements) ? $next_elements : [$next_elements];
        foreach ($next_elements as $next_element) {
            throw_if(! $next_element instanceof BpmnElement && ! is_string($next_element),
                \InvalidArgumentException::class,
                get_class($element).'::advance returned invalid result type `'.get_debug_type($next_elements).'`. '.
                'Allowed return types are: array<BpmnElement|string>, BpmnElement, string, empty values (null, false, etc).'
            );

            try {
                $this->advance($next_element);
            } catch (\Throwable $e) {
                $next_element_name = $next_element instanceof BpmnElement ? $next_element->debug_name : $next_element;
                report(new BpmnExecutionException("Failed advancing next element $next_element_name for process {$this->process->debug_name}, error: {$e->getMessage()}", 0, $e));
            }
        }
    }

    protected function findElementOrFail(string $id): BpmnElement
    {
        if ($element = $this->current_state->getChild($id)) {
            return $element;
        }

        if ($id == $this->current_state->id) {
            return $this->current_state;
        }

        throw new BpmnExecutionException("BPMN element with id '$element' not found in {$this->current_state->debug_name} at process {$this->process->debug_name}.");
    }
}
