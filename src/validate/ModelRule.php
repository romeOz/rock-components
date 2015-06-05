<?php

namespace rock\components\validate;


use rock\components\Model;
use rock\validate\rules\Rule;

abstract class ModelRule extends Rule
{
    /** @var  Model */
    public $model;
    /**
     * Name of attribute.
     * @var string
     */
    public $attribute;
}