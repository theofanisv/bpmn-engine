<?php

namespace Theograms\BpmnManager\Elements;

abstract class UnprocessableBpmnElement extends BpmnElement
{
    public static bool $processable = false;

    public static bool $parse_children = false;

    public function advance()
    {
        throw new \RuntimeException(static::class.' is an unprocessable BPMN element. You should not call `::advance` on it.');
    }
}
