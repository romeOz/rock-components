<?php

namespace rock\components\sanitize;


use rock\components\Model;
use rock\sanitize\rules\Rule;

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