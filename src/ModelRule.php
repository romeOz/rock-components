<?php

namespace rock\components;


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