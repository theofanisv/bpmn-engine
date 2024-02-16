<?php

namespace Theograms\BpmnManager\Elements;

/**
 * Supports multiple incoming flows and multiple outgoing flows in the same element.
 */
class ParallelGateway extends Gateway
{
    public const TAG = 'parallelGateway';

    public function advance(BpmnElement $root_element)
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        $this->when($this->hasState(State::START, State::WAIT), function () use ($root_element) {
            // If incoming flows are one, then this element is a diverging node and no checks are needed.
            // When it has multiple incoming flows then this is a merging node.
            // So we require all of them to be completed, so we can proceed with this element.
            if ($this->incoming->count() > 1) {
                $all_merged = $this->incoming->every(fn (array $data, string $id) => $root_element->getChild($id)->hasState(State::END));
                if (! $all_merged) {
                    $this->changeState(State::WAIT);

                    return;
                }
            }

            $this->changeState(State::END);
        });

        return $this->has_ended
            ? $this->outgoing->keys()->toArray()
            : null;
    }
}
