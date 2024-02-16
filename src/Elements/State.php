<?php

namespace Theograms\BpmnManager\Elements;

enum State: string
{
    case START = 'start';
    case WAIT = 'wait';
    case END = 'end';

    public function toString(): string
    {
        return $this->value;
    }
}
