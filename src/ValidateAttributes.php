<?php

namespace rock\components;


use rock\base\ObjectTrait;
use rock\components\sanitize\ModelSanitize;
use rock\components\validate\ModelValidate;
use rock\helpers\Instance;
use rock\sanitize\Sanitize;
use rock\validate\Validate;

class ValidateAttributes
{
    use ObjectTrait;

    /** @var  Model */
    public $model;
    public $useLabelsAsPlaceholders = true;
    /** @var Validate|array|string  */
    public $validate = 'validate';
    /** @var Sanitize|array|string  */
    public $sanitize = 'sanitize';

    public function validate(array $attributeNames, array $rules)
    {
        $messages = [];
        $errors = $this->model->getErrors();
        if (isset($rules['messages'])) {
            $messages = $rules['messages'];
        }
        foreach ($attributeNames as $name) {
//            if (!isset($this->model->$name)) {
//                $this->model->$name = null;
//            }
            $placeholders = [];
            if (isset($rules['placeholders'])) {
                $placeholders = $rules['placeholders'];
            }
            if ($this->useLabelsAsPlaceholders && !isset($placeholders['name'])) {
                if (($label = $this->model->attributeLabels()) && isset($label[$name])) {
                    $placeholders['name'] = $label[$name];
                }
            }
            foreach ($rules as $key => $ruleName) {
                $onlySanitize = false;
                if ($key === 'placeholders' || $key === 'messages' || $key === 'one' || $key === 'when') {
                    continue;
                }
                if ($ruleName === 'one') {
                    $rules[$ruleName] = 0;
                    continue;
                }
                $args = [];
                if (is_string($key)) {
                    if (!is_array($ruleName)) {
                        throw new ModelException('Arguments must be `array`');
                    }
                    $args = $ruleName;
                    $ruleName = $key;
                }

                if (is_string($ruleName) && $ruleName[0] === '!') {
                    $ruleName = ltrim($ruleName, '!');
                    $onlySanitize = true;
                }
                // closure
                if ($ruleName instanceof \Closure) {
                    array_unshift($args, $name, $this->model->$name);

                    call_user_func_array(\Closure::bind($ruleName, $this->model), $args);
                    continue;
                }

                // method
                if (method_exists($this->model, $ruleName)) {
                    array_unshift($args, $name, $this->model->$name);
                    call_user_func_array([$this->model, $ruleName], $args);
                    continue;
                }

                /** @var ModelValidate $validate */
                $validate = clone Instance::ensure($this->validate, ModelValidate::className());
                /** @var ModelSanitize $sanitize */
                $sanitize = clone Instance::ensure($this->sanitize, ModelSanitize::className());

                if (!$validate->existsRule($ruleName) && !$sanitize->existsRule($ruleName)) {
                    throw new ModelException("Unknown rule: {$ruleName}");
                }

                if (!$onlySanitize && $validate->existsRule($ruleName)) {
                    if ($validate instanceof ModelValidate) {
                        $validate->model = $this;
                        $validate->attribute = $name;
                    }

                    // rule
                    if ($placeholders) {
                        $validate->placeholders($placeholders);
                    }
                    if ($messages) {
                        $validate->messages($messages);
                    }
                    $validate = call_user_func_array([$validate, $ruleName], $args);
                    if (!$validate->validate($this->model->$name)) {
                        $this->model->addError($name, $validate->getFirstError());
                    }
                }

                if ($this->model->hasErrors()) {
                    continue;
                }

                if ($sanitize->existsRule($ruleName)) {
                    if ($sanitize instanceof ModelSanitize) {
                        $sanitize->model = $this;
                        $sanitize->attribute = $name;
                    }

                    $this->model->$name = call_user_func_array([$sanitize, $ruleName], $args)->sanitize($this->model->$name);
                }

            }
            if (isset($rules['one'])) {
                if ((is_int($rules['one']) || $rules['one'] === $name) && $errors !== $this->model->getErrors()) {
                    return false;
                }
            }
        }

        if (isset($rules['when']) && $errors === $this->model->getErrors()) {
            return $this->validate($attributeNames, $rules['when']);
        }
        return true;
    }
}