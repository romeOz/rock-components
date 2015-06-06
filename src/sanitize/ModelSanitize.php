<?php

namespace rock\components\sanitize;


use rock\components\Model;
use rock\sanitize\Sanitize;

/**
 * Model sanitize.
 *
 * @method static Sanitize mongoId()
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

    protected function defaultRules()
    {
        $rules = [];
        if (class_exists('\rock\mongodb\sanitize\rules\MongoIdRule')) {
            $rules['mongoId'] = \rock\mongodb\sanitize\rules\MongoIdRule::className();
        }
        return $rules + parent::defaultRules();
    }
}