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

    protected function defaultRules()
    {
        $rules = [];
        if (class_exists('\rock\mongodb\validate\rules\MongoIdRule')) {
            $rules['mongoId'] = [
                'class' => \rock\mongodb\validate\rules\MongoIdRule::className(),
                'locales' => [
                    'en' => \rock\mongodb\validate\locale\en\MongoIdLocale::className(),
                    'ru' => \rock\mongodb\validate\locale\ru\MongoIdLocale::className(),
                ]
            ];
        }

        if (class_exists('\rock\db\validate\rules\Unique')) {
            $rules['unique'] = [
                'class' => \rock\db\validate\rules\Unique::className(),
                'locales' => [
                    'en' => \rock\db\validate\locale\en\Unique::className(),
                    'ru' => \rock\db\validate\locale\ru\Unique::className(),
                ]
            ];
        }
        return $rules + parent::defaultRules();
    }
}