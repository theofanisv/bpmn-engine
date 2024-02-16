<?php

namespace Theograms\BpmnManager\Elements;

use Theograms\BpmnManager\Exceptions\BpmnExecutionException;

class EndEvent extends BpmnElement
{
    public const TAG = 'endEvent';

    public function advance()
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        $this->when($this->hasState(State::START), function () {
            $this->changeState(State::END);
        });

        if ($this->has_ended) {
            return throw_unless($this->parent_id, BpmnExecutionException::class, "{$this->debug_name}: does not have `parent_id`.");
        }

        return null;
    }
}
