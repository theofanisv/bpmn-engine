<?php

namespace Theograms\BpmnManager\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Theograms\BpmnManager\Elements\BpmnElement;
use Theograms\BpmnManager\Elements\State;
use Theograms\BpmnManager\Models\Process;

class BpmnElementStateChangingEvent
{
    use Dispatchable;

    public function __construct(
        public Process $process,
        public BpmnElement $bpmn_element,
        public ?State $new_state)
    {
    }
}
