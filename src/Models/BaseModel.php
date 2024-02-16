<?php

namespace Theograms\BpmnManager\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('bpmn-manager.table_prefix').parent::getTable();
    }
}
