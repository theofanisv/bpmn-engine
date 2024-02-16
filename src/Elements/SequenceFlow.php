<?php

namespace Theograms\BpmnManager\Elements;

use Theograms\BpmnManager\Exceptions\BpmnExecutionException;

/**
 * @property string $sourceRef
 * @property string $targetRef
 * @property ?bool $selected
 * @property ?bool $evaluated Whether the result of the condition has been received, if one exists.
 * @property ?bool $execute_previous_when_evaluated When this is evaluated, instead of going forward it will move execution to the previous element. Used for exclusive gateways.
 */
class SequenceFlow extends Flow
{
    public const TAG = 'sequenceFlow';

    public function advance(\Theograms\BpmnManager\Models\Process $process)
    {
        $this->when($this->hasState(null), function () {
            $this->changeState(State::START);
        });

        $this->when($this->hasState(State::START, State::WAIT), function () use ($process) {
            if ($this->isConditional()) {
                $input = $process->getInputFor($this);
                $condition = $input['condition'] ?? null;

                if (! isset($condition)) {
                    $this->changeState(State::WAIT);

                    return;
                }

                $this->selected = boolval($condition);
                $this->evaluated = true;
            }

            $this->changeState(State::END);
        });

        if ($this->has_ended) {
            if ($this->execute_previous_when_evaluated) {
                return throw_unless($this->sourceRef, BpmnExecutionException::class, "{$this->debug_name}: Does not have a sourceRef.");
            }

            if ($this->isConditional()) {
                return $this->selected
                    ? throw_unless($this->targetRef, BpmnExecutionException::class, "{$this->debug_name}: Does not have a targetRef.")
                    : null;
            }

            return throw_unless($this->targetRef, BpmnExecutionException::class, "{$this->debug_name}: Does not have a targetRef.");
        }

        return null;
    }

    public function isConditional()
    {
        return $this->attributes['ti:conditional'] ?? false;
    }
}
