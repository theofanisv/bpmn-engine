<?php

namespace Theograms\BpmnManager\Elements;

class Task extends BpmnElement
{
    public const TAG = 'task';

    public function advance(\Theograms\BpmnManager\Models\Process $process)
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        $this->when($this->hasState(State::START, State::WAIT), function () use ($process) {
            $input = $process->getInputFor($this);

            if (($input['state'] ?? null) != State::END->value) {
                $this->changeState(State::WAIT);

                return;
            }

            $this->changeState(State::END);
        });

        if ($this->has_ended) {
            $flow = $this->outgoing->first();
            $this->verifyOutgoingFlow($flow);

            return $flow['id'];
        }

        return null;
    }
}
