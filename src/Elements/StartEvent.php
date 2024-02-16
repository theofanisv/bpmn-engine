<?php

namespace Theograms\BpmnManager\Elements;

class StartEvent extends BpmnElement
{
    public const TAG = 'startEvent';

    public function advance()
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        return $this->when($this->hasState(State::START), function () {
            $flow = $this->outgoing->first();
            $this->verifyOutgoingFlow($flow);
            $this->changeState(State::END);

            return $flow['id'];
        }, fn () => false);
    }
}
