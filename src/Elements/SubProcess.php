<?php

namespace Theograms\BpmnManager\Elements;

use Theograms\BpmnManager\Exceptions\BpmnExecutionException;

class SubProcess extends BpmnElement
{
    public const TAG = 'subProcess';

    public function advance(BpmnElement $root_element)
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        return $this->when($this->hasState(State::START), function () use ($root_element) {
            $end_event = $root_element->children_elements->firstWhere(fn (BpmnElement $e) => $e->tag == EndEvent::TAG && $e->parent_id == $this->id);
            throw_unless($end_event, BpmnExecutionException::class, "{$this->debug_name} does not have an EndEvent child.");
            if ($end_event->hasState(State::END)) {
                $flow = $this->outgoing->first();
                $this->verifyOutgoingFlow($flow);
                $this->changeState(State::END);

                return $flow['id'];
            }

            $start_event = $root_element->children_elements->firstWhere(fn (BpmnElement $e) => $e->tag == StartEvent::TAG && $e->parent_id == $this->id);
            throw_unless($start_event, BpmnExecutionException::class, "{$this->debug_name} does not have a StartEvent child.");

            return $start_event->hasState(State::END)
                ? $root_element->children_elements->where(['parent_id' => $this->id])->whereIn('state', [State::WAIT, State::START])->all()
                : $start_event;
        }, fn () => false);
    }
}
