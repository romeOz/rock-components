<?php

namespace rock\components\validate;


use rock\components\Model;
use rock\validate\Validate;

/**
 * Model validate.
 *
 * @method static Validate unique($targetAttribute = null, $targetClass = null, $filter = null)
 * @method static Validate mongoId()
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