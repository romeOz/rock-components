<?php

namespace rock\components;


use rock\validate\Validate;

/**
 * Model validate.
 *
 * @method static Validate unique(Model $m, $targetAttribute = null, $targetClass = null, $filter = null)
 * @method static Validate mongoId(Model $m)
 *
 * @package rock\validate
 */
class ModelValidate extends Validate
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