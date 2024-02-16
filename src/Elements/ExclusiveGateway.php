<?php

namespace Theograms\BpmnManager\Elements;

use Theograms\BpmnManager\Exceptions\BpmnExecutionException;

class ExclusiveGateway extends Gateway
{
    public const TAG = 'exclusiveGateway';

    public function advance(BpmnElement $root_element)
    {
        $this->when($this->hasState(null), function () use ($root_element) {
            $this->changeState(State::START);

            // Initialize outgoing flows for evaluation.
            if ($this->outgoing->count() > 1) {
                $this->outgoing->each(function (array $data, string $id) use ($root_element) {
                    if ($id == $this->default) {
                        return;
                    }

                    $element = $root_element->getChild($id, SequenceFlow::class);
                    $element->execute_previous_when_evaluated = true;
                    $element->evaluated = false;
                    $root_element->addChild($element);
                });
            }
        });

        if ($this->hasState(State::START, State::WAIT)) {
            $next = $this->advanceToEnd($root_element);
            if ($this->hasState(State::WAIT)) {
                return $next;
            }
        }

        if ($this->has_ended) {
            return $this->outgoing->count() > 1
                ? $this->outgoing->first(
                    fn (array $data, string $id) => $root_element->getChild($id, SequenceFlow::class)->selected,
                    fn () => $root_element->getChild($this->default)
                )['id']
                : $this->outgoing->first()['id'];
        }

        return null;
    }

    private function advanceToEnd(BpmnElement $root_element)
    {
        // If incoming flows are one, then this element is a diverging node and no checks are needed.
        // When incoming flows are multiple it means this is a merging node.
        // So this means we require at least one to have ended so this element can proceed.
        if ($this->incoming->count() > 1) {
            $any_completed = $this->incoming->first(fn (array $data, string $id) => $root_element->getChild($id)->has_ended);
            if (! $any_completed) {
                $this->changeState(State::WAIT);

                return null;
            }
        }

        // If outgoing flows are one, then this element is a merging node and no checks are needed.
        // When it has multiple outgoing flows it means this is a diverging node.
        // So this means we require all of them to have evaluated conditions and only one of them to be true.
        if ($this->outgoing->count() > 1) {
            $outgoing_flows = $this->outgoing->map(fn (array $data, string $id) => $root_element->getChild($id, SequenceFlow::class));

            // Find all outgoing flows that must be evaluated. Eventually only one should evaluate to true.
            // Wait for all flows to be evaluated and exclude the default one (if exists).
            // To overcome an infinite loop from this to every outgoing and back to this, exclude all outgoing, which have reached waiting state.
            $not_evaluated_flows = $outgoing_flows->reject(fn (SequenceFlow $e) => $e->evaluated || $e->hasState(State::WAIT) || $e->id == $this->default);
            if ($not_evaluated_flows->isNotEmpty()) {
                $this->changeState(State::WAIT);

                return $not_evaluated_flows->keys()->all();
            }

            $selected_outgoing = $outgoing_flows->filter(fn (SequenceFlow $e) => $e->selected);
            info('selecting', [$selected_outgoing]);
            throw_if($selected_outgoing->count() > 1, BpmnExecutionException::class, "{$this->debug_name}: Multiple selected flows ({$selected_outgoing->map->id->implode(', ')}), there must be only one or none (default).");
            throw_if($selected_outgoing->isEmpty() && empty($this->default), BpmnExecutionException::class, "{$this->debug_name}: No outgoing flow selected and there is not default outgoing flow.");

            // When one outgoing found, then “unlock” it for execution.
            if ($selected_outgoing->count() == 1) {
                /** @var SequenceFlow $flow */
                $flow = $selected_outgoing->first();
                $flow->execute_previous_when_evaluated = false;
                $root_element->addChild($flow);
            }
        }

        $this->changeState(State::END);

        return null;
    }
}
