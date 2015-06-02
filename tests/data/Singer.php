<?php

namespace rockunit\data;
use rock\components\Model;


/**
 * Singer
 */
class Singer extends Model
{
    public $firstName;
    public $lastName;
    public $test;
    public function rules()
    {
        return [
            [['lastName'], 'default', 'value' => 'Lennon'],
            [['lastName'], 'required'],
            [['underscore_style'], 'yii\captcha\CaptchaValidator'],
            [['test'], 'required', 'when' => function($model) { return $model->firstName === 'cebe'; }],
        ];
    }
}