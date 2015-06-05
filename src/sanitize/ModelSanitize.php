<?php

namespace rock\components\sanitize;


use rock\components\Model;
use rock\sanitize\Sanitize;

/**
 * Model sanitize.
 *
 * @method static Sanitize mongoId()
 *
 * @package rock\validate
 */
class ModelSanitize extends Sanitize
{
    /** @var  Model */
    public $model;
    /**
     * Name of attribute.
     * @var string
     */
    public $attribute;

    /**
     * @inheritdoc
     */
    protected function getInstanceRule($name, array $arguments)
    {
        $rule = parent::getInstanceRule($name, $arguments);
        if ($rule instanceof ModelRule) {
            $rule->model = $this->model;
            $rule->attribute = $this->attribute;
        }
        return $rule;
    }
}