<?php

namespace Theograms\BpmnManager\Services;

use Illuminate\Log\Logger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Theograms\BpmnManager\Elements\Association;
use Theograms\BpmnManager\Elements\BpmnElement;
use Theograms\BpmnManager\Elements\BusinessRuleTask;
use Theograms\BpmnManager\Elements\EndEvent;
use Theograms\BpmnManager\Elements\ExclusiveGateway;
use Theograms\BpmnManager\Elements\InclusiveGateway;
use Theograms\BpmnManager\Elements\Lane;
use Theograms\BpmnManager\Elements\LaneSet;
use Theograms\BpmnManager\Elements\ManualTask;
use Theograms\BpmnManager\Elements\ParallelGateway;
use Theograms\BpmnManager\Elements\Process as ProcessElement;
use Theograms\BpmnManager\Elements\ReceiveTask;
use Theograms\BpmnManager\Elements\ScriptTask;
use Theograms\BpmnManager\Elements\SendTask;
use Theograms\BpmnManager\Elements\SequenceFlow;
use Theograms\BpmnManager\Elements\ServiceTask;
use Theograms\BpmnManager\Elements\StartEvent;
use Theograms\BpmnManager\Elements\SubProcess;
use Theograms\BpmnManager\Elements\Task;
use Theograms\BpmnManager\Elements\Text;
use Theograms\BpmnManager\Elements\TextAnnotation;
use Theograms\BpmnManager\Elements\UserTask;
use Theograms\BpmnManager\Exceptions\BpmnManagerException;
use Theograms\BpmnManager\Xml2Array;

class BpmnManager
{
    /**
     * @var array<string, BpmnElement>
     */
    private static array $tags_elements_map = [
        ProcessElement::TAG => ProcessElement::class,
        SubProcess::TAG => SubProcess::class,
        StartEvent::TAG => StartEvent::class,
        EndEvent::TAG => EndEvent::class,
        ExclusiveGateway::TAG => ExclusiveGateway::class,
        ParallelGateway::TAG => ParallelGateway::class,
        InclusiveGateway::TAG => InclusiveGateway::class,
        ScriptTask::TAG => ScriptTask::class,
        UserTask::TAG => UserTask::class,
        ReceiveTask::TAG => ReceiveTask::class,
        SendTask::TAG => SendTask::class,
        ServiceTask::TAG => ServiceTask::class,
        BusinessRuleTask::TAG => BusinessRuleTask::class,
        Task::TAG => Task::class,
        ManualTask::TAG => ManualTask::class,
        SequenceFlow::TAG => SequenceFlow::class,
        LaneSet::TAG => LaneSet::class,
        Lane::TAG => Lane::class,
        TextAnnotation::TAG => TextAnnotation::class,
        Text::TAG => Text::class,
        Association::TAG => Association::class,
    ];

    /**
     * @return class-string<BpmnElement>
     */
    public static function getElementByTag(string $tag): string
    {
        return static::$tags_elements_map[Str::after($tag, 'bpmn:')] ?? throw new \RuntimeException("BPMN element for tag '$tag' is invalid or not supported.");
    }

    /**
     * @param  array|string  $tag When is array then it is handled as `attributes` with one property specifying the tag.
     */
    public static function makeElement(array|string $tag, array $attributes = []): BpmnElement
    {
        [$tag, $attributes] = is_array($tag) ? [$tag['tag'], $tag] : [$tag, $attributes];
        $element = static::getElementByTag($tag);

        return new $element($attributes);
    }

    /**
     * @param  class-string<BpmnElement>|null  $bpmn_element_class
     */
    public static function mapTagToElement(array|string $tag_map, string $bpmn_element_class = null): void
    {
        if (is_string($tag_map)) {
            static::$tags_elements_map[$tag_map] = $bpmn_element_class;
        }

        foreach ($tag_map as $tag => $bpmn_element_class) {
            static::$tags_elements_map[$tag] = $bpmn_element_class;
        }
    }

    public static function logger(): Logger
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Log::channel(config('bpmn-manager.logger'));
    }

    public function parseXml(string $xml): ProcessElement
    {
        $data = Xml2Array::toArray($xml);
        $process_json = Arr::where(data_get($data, '0.children'), fn (array $element) => ($element['tag'] ?? null) == 'bpmn:process');
        throw_if(empty($process_json), BpmnManagerException::class, "BPMN diagram does not contain any 'bpmn:process'.");
        throw_if(count($process_json) > 1, BpmnManagerException::class, "BPMN diagram contains multiple 'bpmn:process', only one is supported.");
        $process_json = head($process_json);
        $elements_json = array_pull($process_json, 'children');
        $process = new ProcessElement($process_json);

        /**
         * Elements that will be re-processed later.
         */
        $elements_with_children = collect($elements_json)
            ->filter(function (array $element_json) use ($process) {
                // Exclude children from first cycle parsing.
                // Firstly, we need to discover all elements and later map every element's children.
                $element = static::makeElement(['children' => [], 'parent_id' => $process->id] + $element_json);
                if (! $element::$processable) {
                    return false;
                }
                $process->addChild($element);

                return ! empty($element_json['children']);
            })
            ->toArray();

        while (! empty($elements_with_children = $this->parseElementsWithChildren($elements_with_children, $process)));

        return $process;
    }

    private function parseElementsWithChildren(array $elements_with_children_json, ProcessElement $process): array
    {
        $new_elements_with_children = [];

        foreach ($elements_with_children_json as $element_json) {
            $parent_element = $process->getChild($element_json['id']);

            foreach ($element_json['children'] as $index => $child_json) {
                $child_json['tag'] = Str::after($child_json['tag'], 'bpmn:');
                $child_json['parent_id'] = $parent_element->id;

                if ($child_json['tag'] == 'incoming') {
                    $child_json['id'] = $child_json['body'];
                    unset($child_json['body']);
                    $parent_element->incoming = $parent_element->incoming->put($child_json['id'], $child_json);

                } elseif ($child_json['tag'] == 'outgoing') {
                    $child_json['id'] = $child_json['body'];
                    unset($child_json['body']);
                    $parent_element->outgoing = $parent_element->outgoing->put($child_json['id'], $child_json);

                } else {
                    if (! $parent_element::$parse_children) {
                        continue;
                    }

                    throw_if(empty($child_json['id']), BpmnManagerException::class, "Child at index $index of #{$parent_element->id} does not have id.");
                    $element = $process->getChild($child_json['id'], false); // This is not needed, remains as extra validation.
                    if (empty($element)) {
                        $element = static::makeElement(['children' => []] + $child_json);
                        if (! $element::$processable) {
                            continue;
                        }

                        $process->addChild($element);
                        if (! empty($child_json['children'])) {
                            $new_elements_with_children[] = $child_json;
                        }
                    }
                }
            }

            $process->addChild($parent_element); // Update the stored version of this element.
        }

        return $new_elements_with_children;
    }
}
