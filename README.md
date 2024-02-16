BPMN Manager
============

Provide BPMN diagrams (xml) and get the course of execution step-by-step.

# Supported BPMN Elements

| Type    | Element                   | Tag                | Comment           |
|---------|---------------------------|--------------------|-------------------|
| Gateway | Exclusive Gateway         | `exclusiveGateway` |                   |
| ^       | Parallel Gateway          | `parallelGateway`  |                   |
| ^       | Inclusive Gateway         | `inclusiveGateway` |                   |
| Task    | <                         | `task`             |                   |
| ^       | Script Task               | `scriptTask`       | Handled like Task |
| ^       | User Task                 | `userTask`         | ^                 |
| ^       | Manual Task               | `manualTask`       | ^                 |
| ^       | Business Rule Task        | `businessRuleTask` | ^                 |
| ^       | Service Task              | `serviceTask`      | ^                 |
| ^       | Send Task                 | `sendTask`         | ^                 |
| ^       | Receive Task              | `receiveTask`      | ^                 |
| Event   | Start Event               | `startEvent`       |                   |
| ^       | End Event                 | `endEvent`         |                   |
| Flow    | Sequence (w\ conditional) | `sequenceFlow`     |                   |
| Process | <                         | `process`          |                   |
| ^       | Subprocess                | `subProcess`       |                   |

## Unprocessable Elements

The following elements are recognised from the engine, but are excluded from processing.

| Type | Element     | Tag              | Comment |
|------|-------------|------------------|---------|
| Lane | <           | `lane`           |         |
| ^    | Lane Set    | `laneSet`        |         |
| Text | <           | `text`           |         |
| ^    | Annotation  | `textAnnotation` |         |
| ^    | Association | `association`    |         |

> [!WARNING]
> Any types of elements not mentioned above will cause an error!

## Supporting new elements

Create appropriate class `Theograms\BpmnManager\Elements\NewElement.php` or you can create your own and load it via
`Theograms\BpmnManager\BpmnManager::mapTagToElement($new_xml_tag, NewElement::class)`. In both cases it is required that
the class `extends Theograms\BpmnManager\Elements\BpmnElement`. Lastly, implement the `advance` method.

# Limits

Essentially there is no limit the size of the BPMN diagrams that can be processed. Size of diagrams is considered the
total amount of elements.
The only actual limit is the processing power of the host machine in relation with the amount of processes being
processed simultaneously.

Definitions' & Processes' default migration provide a column of MEDIUMTEXT (16 MB).
If your application handles even bigger BPMN diagrams then create a migration with the following.

```php
Schema::table(config('bpmn-manager.table_prefix') . 'definitions', function (Blueprint $table) {
    $table->longText('definition')->change(); // 4 GB
});

Schema::table(config('bpmn-manager.table_prefix') . 'processes', function (Blueprint $table) {
    $table->longText('state')->change; // 4 GB
});
```
