<?php

namespace Theograms\BpmnManager\Elements;

use Theograms\BpmnManager\Exceptions\BpmnExecutionException;

class Process extends BpmnElement
{
    public const TAG = 'process';

    public function advance()
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        return $this->when($this->hasState(State::START), function () {
            $end_event = $this->children_elements->firstWhere(fn (BpmnElement $e) => $e->tag == EndEvent::TAG && $e->parent_id == $this->id);
            throw_unless($end_event, BpmnExecutionException::class, "{$this->debug_name} does not contain an EndEvent element.");
            if ($end_event->hasState(State::END)) {
                $this->changeState(State::END);

                return false;
            }

            $start_event = $this->children_elements->firstWhere(fn (BpmnElement $e) => $e->tag == StartEvent::TAG && $e->parent_id == $this->id);
            throw_unless($start_event, BpmnExecutionException::class, "{$this->debug_name} does not contain a StartEvent element.");

            return $start_event->hasState(State::END)
                ? $this->children_elements->whereIn('state', [State::WAIT, State::START])->all()
                : $start_event;
        }, fn () => false);
    }
}
