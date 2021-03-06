<?php
namespace rock\components;

use rock\helpers\Inflector;
use rock\sanitize\Sanitize;
use rock\validate\Validate;

/**
 * Model is the base class for data models.
 *
 * Model implements the following commonly used features:
 *
 * - attribute declaration: by default, every public class member is considered as
 *   a model attribute
 * - attribute labels: each attribute may be associated with a label for display purpose
 * - massive attribute assignment
 * - scenario-based validation
 *
 * Model also raises the following events when performing data validation:
 *
 * - {@see \rock\components\Model::EVENT_BEFORE_VALIDATE}: an event raised at the beginning of {@see \rock\components\Model::validate()}
 * - {@see \rock\components\Model::EVENT_AFTER_VALIDATE} : an event raised at the end of {@see \rock\components\Model::validate()}
 *
 * You may directly use Model to store model data, or extend it with customization.
 *
 * @property array $attributes  Attribute values (name => value).
 * @property array $errors      An array of errors for all attributes. Empty array is returned if no error. The
 *          result is a two-dimensional array. See {@see \rock\components\Model::getErrors()} for detailed description. This property is read-only.
 * @property array $firstErrors The first errors. An empty array will be returned if there is no error. This
 *          property is read-only.
 * @property \ArrayIterator $iterator    An iterator for traversing the items in the list. This property is
 *          read-only.
 * @property string $scenario    The scenario that this model is in.
 * Defaults to {@see \rock\components\Model::DEFAULT_SCENARIO} .
 * @property \ArrayObject $validators  All the validators declared in the model. This property is read-only.
 *
 * @package models
 */
class Model implements \IteratorAggregate, \ArrayAccess, Arrayable, ComponentsInterface
{
    use ComponentsTrait {
        ComponentsTrait::__get as parentGet;
    }

    use ArrayableTrait;

    /**
     * The name of the default scenario.
     */
    const DEFAULT_SCENARIO = 'default';

    /**
     * @event ModelEvent an event raised at the beginning
     * of {@see \rock\components\Model::validate()}.
     */
    const EVENT_BEFORE_VALIDATE = 'beforeValidate';

    /**
     * @event Event an event raised at the end
     * of {@see \rock\components\Model::validate()}
     */
    const EVENT_AFTER_VALIDATE = 'afterValidate';

    /** @var Validate|array|string */
    protected $validate = 'validate';
    /** @var Sanitize|array|string */
    protected $sanitize = 'sanitize';
    /**
     * @var string current scenario
     */
    protected $_scenario = self::DEFAULT_SCENARIO;
    /**
     * @var array validation errors (attribute name => array of errors)
     */
    protected $_errors = [];
    protected $useLabelsAsPlaceholders = true;


    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by {@see \rock\components\Model::validate()} to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * Each rule is an array with the following structure:
     *
     * ```php
     * [
     *  'type',
     *  ['attribute1', 'attribute2'],
     *  rule_1,
     *  rule_2,
     *  ...
     *  'on' => ['scenario1', 'scenario2'],
     * ]
     * ```
     *
     * where
     *
     * - attribute list: required, specifies the attributes array to be validated, for single attribute you can pass string;
     * - validator type: required, specifies the validator to be used. It can be a built-in validator name,
     * a method name of the model class, an anonymous function, or a validator class name.
     * - on: optional, specifies the {@see \rock\components\Model::$scenario} array when the validation
     * rule can be applied. If this option is not set, the rule will apply to all scenarios.
     * - additional name-value pairs can be specified to initialize the corresponding validator properties.
     * Please refer to individual validator class API for possible properties.
     *
     * A validator can be either an object of a class extending {@see \rock\validate\Validate}, or a model class method
     * (called *inline validator*) that has the following signature:
     *
     * ```php
     * // $params refers to validation parameters given in the rule
     * function validatorName($attribute, $params)
     * ```
     *
     * In the above `$attribute` refers to currently validated attribute name while `$params` contains an array of
     * validator configuration options such as `max` in case of `string` validator. Currently validate attribute value
     * can be accessed as `$this->[$attribute]`.
     *
     * Below are some examples:
     *
     * ```php
     * [
     *  // built-in "required" validator
     *  [['username', 'password'], 'required'],
     *  // built-in "string" validator customized with "min" and "max" properties
     *  ['username', 'string', 'min' => [3], 'max' => [12]],
     *  // built-in "compare" validator that is used in "register" scenario only
     *  ['password', 'confirm' => [$this->password], 'scenarios' => ['signup']],
     *  // an inline validator defined via the "authenticate()" method in the model class
     *  ['password', 'authenticate', 'scenarios' => ['login']],
     * ];
     * ```
     *
     * Note, in order to inherit rules defined in the parent class, a child class needs to
     * merge the parent rules with child rules using functions such as `array_merge()`.
     *
     * @return array validation rules
     * @see \rock\base\Model::$scenario
     */
    public function rules()
    {
        return [];
    }

    /**
     * Sets the scenario for the model.
     * Note that this method does not check if the scenario exists or not.
     * The method {@see \rock\components\Model::validate()} will perform this check.
     * @param string $value the scenario that this model is in.
     */
    public function setScenario($value)
    {
        $this->_scenario = $value;
    }

    /**
     * Returns the form name that this model class should use.
     *
     * The form name is mainly used by {@see \rock\widgets\ActiveForm} to determine how to name
     * the input fields for the attributes in a model. If the form name is "A" and an attribute
     * name is "b", then the corresponding input name would be "A[b]". If the form name is
     * an empty string, then the input name would be "b".
     *
     * By default, this method returns the model class name (without the namespace part)
     * as the form name. You may override it when the model is used in different forms.
     *
     * @return string the form name of this model class.
     */
    public function formName()
    {
        $reflector = new \ReflectionClass($this);

        return $reflector->getShortName();
    }

    protected $_attributeNames;

    /**
     * Returns the list of attribute names.
     * By default, this method returns all public non-static properties of the class.
     * You may override this method to change the default behavior.
     *
     * @return array list of attribute names.
     */
    public function attributes()
    {
        if (isset($this->_attributeNames)) {
            return $this->_attributeNames;
        }
        $class = new \ReflectionClass($this);
        $names = [];
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if (!$property->isStatic()) {
                $names[] = $name;
            }
        }

        return $this->_attributeNames = $names;
    }

    /**
     * Returns the attribute labels.
     *
     * Attribute labels are mainly used for display purpose. For example, given an attribute
     * `firstName`, we can declare a label `First Name` which is more user-friendly and can
     * be displayed to end users.
     *
     * By default an attribute label is generated using {@see \rock\components\Model::generateAttributeLabel()}.
     * This method allows you to explicitly specify attribute labels.
     *
     * Note, in order to inherit labels defined in the parent class, a child class needs to
     * merge the parent labels with child labels using functions such as `array_merge()`.
     *
     * @return array attribute labels (name => label)
     * @see generateAttributeLabel
     */
    public function attributeLabels()
    {
        return [];
    }

    /**
     * Returns the attribute hints.
     *
     * Attribute hints are mainly used for display purpose. For example, given an attribute
     * `isPublic`, we can declare a hint `Whether the post should be visible for not logged in users`,
     * which provides user-friendly description of the attribute meaning and can be displayed to end users.
     *
     * Unlike label hint will not be generated, if its explicit declaration is omitted.
     *
     * Note, in order to inherit hints defined in the parent class, a child class needs to
     * merge the parent hints with child hints using functions such as `array_merge()`.
     *
     * @return array attribute hints (name => hint)
     */
    public function attributeHints()
    {
        return [];
    }

    /**
     * Performs the data validation.
     *
     * This method executes the validation rules applicable to the current {@see \rock\components\Model::$scenario}.
     * The following criteria are used to determine whether a rule is currently applicable:
     *
     * - the rule must be associated with the attributes relevant to the current scenario;
     * - the rules must be effective for the current scenario.
     *
     * This method will call {@see \rock\components\Model::beforeValidate()} and {@see \rock\components\Model::afterValidate()} before and
     * after the actual validation, respectively. If {@see \rock\components\Model::beforeValidate()} returns false,
     * the validation will be cancelled and {@see \rock\components\Model::afterValidate()} will not be called.
     *
     * Errors found during the validation can be retrieved via {@see \rock\components\Model::getErrors()}, {@see \rock\components\Model::getFirstErrors()}
     * and {@see \rock\components\Model::getFirstError()}.
     *
     * @param array $attributeNames list of attribute names that should be validated.
     * If this parameter is empty, it means any attribute listed in the applicable
     * validation rules should be validated.
     * @param bool $clearErrors whether to call {@see \rock\components\Model::clearErrors()} before performing validation
     * @return bool whether the validation is successful without any error.
     * @throws ModelException
     */
    public function validate(array $attributeNames = null, $clearErrors = true)
    {
        if ($clearErrors) {
            $this->clearErrors();
        }
        if (empty($attributeNames)) {
            $attributeNames = $this->attributes();
        }

        if (!$this->beforeValidate()) {
            return false;
        }
        if (!class_exists('\rock\validate\Validate')) {
            throw new ModelException(ModelException::NOT_INSTALL_LIBRARY, ['name' => 'Rock Validate']);
        }
        if (!class_exists('\rock\sanitize\Sanitize')) {
            throw new ModelException(ModelException::NOT_INSTALL_LIBRARY, ['name' => 'Rock Sanitize']);
        }

        $config = [
            'model' => $this,
            'validate' => $this->validate,
            'sanitize' => $this->sanitize,
            'useLabelsAsPlaceholders' => $this->useLabelsAsPlaceholders
        ];
        $validate = new ValidateAttributes($config);

        foreach ($this->getActiveRules() as $rules) {
            $attributes = array_shift($rules);
            if (is_string($attributes)) {
                $attributes = [$attributes];
            }
            $attributes = array_intersect($attributes, $attributeNames);
            if (!$validate->validate($attributes, $rules)) {
                break;
            }
        }

        if ($this->hasErrors()) {
            return false;
        }

        $this->afterValidate();
        return true;
    }

    /**
     * Returns the validators applicable to the current {@see \rock\components\Model::$scenario}.
     * @param string $attribute the name of the attribute whose applicable validators should be returned.
     * If this is null, the validators for ALL attributes in the model will be returned.
     * @return array the validators applicable to the current {@see \rock\components\Model::$scenario}.
     */
    public function getActiveRules($attribute = null)
    {
        $rules = [];
        $scenario = $this->getScenario();

        foreach ($this->rules() as $rule) {

            if (isset($rule['scenarios'])) {
                if (is_string($rule['scenarios']) && $scenario !== $rule['scenarios']) {
                    continue;
                }
                if (is_array($rule['scenarios']) && !in_array($scenario, $rule['scenarios'], true)) {
                    continue;
                }
                unset($rule['scenarios']);
            }

            if ($attribute === null || in_array($attribute, (array)$rule[0], true)) {
                $rules[] = $rule;
            }
        }
        return $rules;
    }

    /**
     * Returns a value indicating whether the attribute is required.
     * This is determined by checking if the attribute is associated with a
     * `required` validation rule in the
     * current {@see \rock\components\Model::$scenario}.
     *
     * Note that when the validator has a conditional validation applied using
     * `when` this method will return
     * `false` regardless of the `when` condition because it may be called be
     * before the model is loaded with data.
     *
     * @param string $attribute attribute name
     * @return boolean whether the attribute is required
     */
    public function isAttributeRequired($attribute)
    {
        foreach ($this->getActiveRules($attribute) as $rule) {
            if (!isset($rule['when']) && array_search('required', $rule, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validates multiple models.
     *
     * This method will validate every model. The models being validated may
     * be of the same or different types.
     * @param array $models the models to be validated
     * @param array $attributeNames list of attribute names that should be validated.
     * If this parameter is empty, it means any attribute listed in the applicable
     * validation rules should be validated.
     * @return boolean whether all models are valid. False will be returned if one
     * or multiple models have validation error.
     */
    public static function validateMultiple($models, $attributeNames = null)
    {
        $valid = true;
        /** @var Model $model */
        foreach ($models as $model) {
            $valid = $model->validate($attributeNames) && $valid;
        }

        return $valid;
    }

    /**
     * This method is invoked before validation starts.
     *
     * The default implementation raises a {@see \rock\components\Model::beforeValidate()} event.
     * You may override this method to do preliminary checks before validation.
     * Make sure the parent implementation is invoked so that the event can be raised.
     * @return boolean whether the validation should be executed. Defaults to true.
     * If false is returned, the validation will stop and the model is considered invalid.
     */
    public function beforeValidate()
    {
        $event = new ModelEvent;
        $this->trigger(self::EVENT_BEFORE_VALIDATE, $event);
        return $event->isValid;
    }

    /**
     * This method is invoked after validation ends.
     *
     * The default implementation raises an {@see \rock\components\Model::afterValidate()} event.
     * You may override this method to do postprocessing after validation.
     * Make sure the parent implementation is invoked so that the event can be raised.
     */
    public function afterValidate()
    {
        $this->trigger(self::EVENT_AFTER_VALIDATE);
    }

    /**
     * Returns a value indicating whether the attribute is safe for massive assignments.
     *
     * @param string $attribute attribute name
     * @return boolean whether the attribute is safe for massive assignments
     */
    public function isAttributeSafe($attribute)
    {
        return in_array($attribute, $this->safeAttributes(), true);
    }

    /**
     * Returns the text label for the specified attribute.
     *
     * @param string $attribute the attribute name
     * @return string the attribute label
     * @see generateAttributeLabel
     * @see attributeLabels
     */
    public function getAttributeLabel($attribute)
    {
        $labels = $this->attributeLabels();

        return isset($labels[$attribute])
            ? $labels[$attribute]
            : $this->generateAttributeLabel($attribute);
    }

    /**
     * Returns the text hint for the specified attribute.
     * @param string $attribute the attribute name
     * @return string the attribute hint
     * @see attributeHints()
     */
    public function getAttributeHint($attribute)
    {
        $hints = $this->attributeHints();
        return isset($hints[$attribute]) ? $hints[$attribute] : '';
    }

    /**
     * Returns a value indicating whether there is any validation error.
     *
     * @param string|null $attribute attribute name. Use null to check all attributes.
     * @return boolean whether there is any error.
     */
    public function hasErrors($attribute = null)
    {
        return $attribute === null
            ? !empty($this->_errors)
            : isset($this->_errors[$attribute]);
    }

    /**
     * Returns the errors for all attribute or a single attribute.
     *
     * @param string $attribute attribute name. Use null to retrieve errors for all attributes.
     * @property array An         array of errors for all attributes. Empty array is returned if no error.
     *                            The result is a two-dimensional array. See {@see \rock\components\Model::getErrors()} for detailed description.
     * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
     *                            Note that when returning errors for all attributes, the result is a two-dimensional array, like the following:
     *
     * ```php
     * array(
     *     'username' => array(
     *         'Username is required.',
     *         'Username must contain only word characters.',
     *     ),
     *     'email' => array(
     *         'Email address is invalid.',
     *     )
     * )
     * ```
     *
     * @see getFirstErrors
     * @see getFirstError
     */
    public function getErrors($attribute = null)
    {
        if ($attribute === null) {
            return $this->_errors;
        }

        return isset($this->_errors[$attribute]) ? $this->_errors[$attribute] : [];
    }

    /**
     * Returns the first error of every attribute in the model.
     *
     * @return array the first errors. An empty array will be returned if there is no error.
     * @see getErrors
     * @see getFirstError
     */
    public function getFirstErrors()
    {
        if (empty($this->_errors)) {
            return [];
        } else {
            $errors = [];
            foreach ($this->_errors as $name => $attributeErrors) {
                if (current($attributeErrors)) {
                    $errors[$name] = current($attributeErrors);
                }
            }
        }

        return $errors;
    }

    /**
     * Returns the first error of the specified attribute.
     *
     * @param string $attribute attribute name.
     * @return string the error message. Null is returned if no error.
     * @see getErrors
     * @see getFirstErrors
     */
    public function getFirstError($attribute)
    {
        return isset($this->_errors[$attribute])
            ? reset($this->_errors[$attribute])
            : null;
    }

    /**
     * Adds a new error to the specified attribute.
     *
     * @param string $attribute attribute name
     * @param string $error new error message
     */
    public function addError($attribute, $error = '')
    {
        $this->_errors[$attribute][] = $error;
    }

    /**
     * Adds a list of errors.
     * @param array $items a list of errors. The array keys must be attribute names.
     * The array values should be error messages. If an attribute has multiple errors,
     * these errors must be given in terms of an array.
     * You may use the result of {@see \rock\components\Model::getErrors()} as the value for this parameter.
     */
    public function addErrors(array $items)
    {
        foreach ($items as $attribute => $errors) {
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->addError($attribute, $error);
                }
            } else {
                $this->addError($attribute, $errors);
            }
        }
    }

    /**
     * Removes errors for all attributes or a single attribute.
     *
     * @param string $attribute attribute name. Use null to remove errors for all attribute.
     */
    public function clearErrors($attribute = null)
    {
        if ($attribute === null) {
            $this->_errors = [];
        } else {
            unset($this->_errors[$attribute]);
        }
    }

    /**
     * Generates a user friendly attribute label based on the give attribute name.
     *
     * This is done by replacing underscores, dashes and dots with blanks and
     * changing the first letter of each word to upper case.
     * For example, 'department_name' or 'DepartmentName' will generate 'Department Name'.
     *
     * @param string $name the column name
     * @return string the attribute label
     */
    public function generateAttributeLabel($name)
    {
        return Inflector::camel2words($name, true);
    }

    /**
     * Returns attribute values.
     *
     * @param array $only list of attributes whose value needs to be returned.
     *                      Defaults to null, meaning all attributes listed in {@see \rock\components\Model::attributes()} will be returned.
     *                      If it is an array, only the attributes in the array will be returned.
     * @param array $exclude list of attributes whose value should NOT be returned.
     * @return array attribute values (name => value).
     */
    public function getAttributes(array $only = [], array $exclude = [])
    {
        $values = [];
        if (empty($only)) {
            $only = $this->attributes();
        }
        foreach ($only as $name) {
            $values[$name] = $this->$name;
        }
        foreach ($exclude as $name) {
            unset($values[$name]);
        }

        return $values;
    }

    /**
     * Sets the attribute values in a massive way.
     *
     * @param array $values attribute values (name => value) to be assigned to the model.
     * @param bool $safeOnly whether the assignments should only be done to the safe attributes.
     * A safe attribute is one that is associated with a validation rule in the current {@see \rock\components\Model::$scenario}.
     * @see safeAttributes()
     * @see \rock\base\Model::attributes()
     */
    public function setAttributes(array $values, $safeOnly = true)
    {
        $attributeNames = array_flip($safeOnly ? $this->safeAttributes() : $this->attributes());
        foreach ($values as $name => $value) {
            if (isset($attributeNames[$name])) {
                $this->$name = $value;
            } elseif ($safeOnly) {
                $this->onUnsafeAttribute($name, $value);
            }
        }
    }

    /**
     * This method is invoked when an unsafe attribute is being massively assigned.
     *
     * The default implementation will log a warning message if `DEBUG` is on.
     * It does nothing otherwise.
     * @param string $name the unsafe attribute name
     * @param mixed $value the attribute value
     */
    public function onUnsafeAttribute($name, $value)
    {
//        if (DEBUG) {
//            Rock::trace(__METHOD__, "Failed to set unsafe attribute '$name' in '" . get_class($this) . "'.");
//        }
    }

    /**
     * Returns the scenario that this model is used in.
     *
     * Scenario affects how validation is performed and which attributes can
     * be massively assigned.
     *
     * @return string the scenario that this model is in.
     * Defaults to {@see \rock\components\Model::DEFAULT_SCENARIO}.
     */
    public function getScenario()
    {
        return $this->_scenario;
    }

    /**
     * Returns the attribute names that are safe to be massively assigned in the current scenario.
     * @return string[] safe attribute names
     */
    public function safeAttributes()
    {
        return $this->attributes();
    }

    protected $isLoad = false;

    /**
     * Check is load data.
     * @return bool
     */
    public function isLoad()
    {
        return $this->isLoad;
    }

    /**
     * Populates the model with the data from end user.
     *
     * The data to be loaded is `$data[formName]`, where `formName` refers to the value of {@see \rock\components\Model::formName()} .
     * If {@see \rock\components\Model::formName()} is empty, the whole `$data` array will be used to populate the model.
     * The data being populated is subject to the safety check by {@see \rock\components\Model::setAttributes()} .
     * @param array $data the data array. This is usually `$_POST` or `$_GET`, but can also be any valid array
     * supplied by end user.
     * @param string $formName the form name to be used for loading the data into the model.
     * If not set, {@see \rock\components\Model::formName()} will be used.
     * @return bool whether the model is successfully populated with some data.
     */
    public function load($data, $formName = null)
    {
        $scope = $formName === null ? $this->formName() : $formName;
        if ($scope === '' && !empty($data)) {
            $this->setAttributes($data);
            return $this->isLoad = true;
        } elseif (isset($data[$scope])) {
            $this->setAttributes($data[$scope]);
            return $this->isLoad = true;
        } else {
            return $this->isLoad = false;
        }
    }

    /**
     * Populates a set of models with the data from end user.
     *
     * This method is mainly used to collect tabular data input.
     * The data to be loaded for each model is `$data[formName][index]`, where `formName`
     * refers to the value of {@see \rock\components\Model::formName()} , and `index` the index of the model in the `$models` array.
     * If {@see \rock\components\Model::formName()} is empty, `$data[index]` will be used to populate each model.
     * The data being populated to each model is subject to the safety check by {@see \rock\components\Model::setAttributes()} .
     * @param array $models the models to be populated. Note that all models should have the same class.
     * @param array $data the data array. This is usually `$_POST` or `$_GET`, but can also be any valid array
     * supplied by end user.
     * @param string $formName the form name to be used for loading the data into the models.
     * If not set, it will use the {@see \rock\components\Model::formName()} value of the first model in `$models`.
     * @return bool whether the model is successfully populated with some data.
     */
    public static function loadMultiple($models, $data, $formName = null)
    {
        /** @var Model $model */
        if ($formName === null) {
            /* @var $first Model */
            $first = reset($models);
            if ($first === false) {
                return false;
            }
            $formName = $first->formName();
        }
        $success = false;
        foreach ($models as $i => $model) {
            /* @var $model Model */
            if ($formName == '') {
                if (!empty($data[$i])) {
                    $model->load($data[$i], '');
                    $success = true;
                }
            } elseif (!empty($data[$formName][$i])) {
                $model->load($data[$formName][$i], '');
                $success = true;
            }
        }
        return $success;
    }

    /**
     * Returns the list of fields that should be returned by default by {@see \rock\components\ArrayableTrait::toArray()} when no specific fields are specified.
     *
     * A field is a named element in the returned array by {@see \rock\components\ArrayableTrait::toArray()}.
     *
     * This method should return an array of field names or field definitions.
     * If the former, the field name will be treated as an object property name whose value will be used
     * as the field value. If the latter, the array key should be the field name while the array value should be
     * the corresponding field definition which can be either an object property name or a PHP callable
     * returning the corresponding field value. The signature of the callable should be:
     *
     * ```php
     * function ($field, $model) {
     *     // return field value
     * }
     * ```
     *
     * For example, the following code declares four fields:
     *
     * - `email`: the field name is the same as the property name `email`;
     * - `firstName` and `lastName`: the field names are `firstName` and `lastName`, and their
     *   values are obtained from the `first_name` and `last_name` properties;
     * - `fullName`: the field name is `fullName`. Its value is obtained by concatenating `first_name`
     *   and `last_name`.
     *
     * ```php
     * return [
     *     'email',
     *     'firstName' => 'first_name',
     *     'lastName' => 'last_name',
     *     'fullName' => function () {
     *         return $this->first_name . ' ' . $this->last_name;
     *     },
     * ];
     * ```
     *
     * In this method, you may also want to return different lists of fields based on some context
     * information. For example, depending on {@see \rock\components\Model::$scenario} or the privilege of the current application user,
     * you may return different sets of visible fields or filter out some fields.
     *
     * The default implementation of this method returns {@see \rock\components\Model::attributes()} indexed by the same attribute names.
     *
     * @return array the list of field names or field definitions.
     * @see \rock\base\ArrayableTrait::toArray()
     */
    public function fields()
    {
        $fields = $this->attributes();

        return array_combine($fields, $fields);
    }

    /**
     * Returns an iterator for traversing the attributes in the model.
     *
     * This method is required by the interface IteratorAggregate.
     *
     * @param array $only
     * @param array $exclude
     * @return \ArrayIterator an iterator for traversing the items in the list.
     */
    public function getIterator(array $only = [], $exclude = [])
    {
        $attributes = $this->getAttributes($only, $exclude);
        return new \ArrayIterator($attributes);
    }

    /**
     * Returns whether there is an element at the specified offset.
     *
     * This method is required by the SPL interface `ArrayAccess`.
     * It is implicitly called when you use something like `isset($model[$offset])`.
     *
     * @param mixed $offset the offset to check on
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->$offset !== null;
    }

    /**
     * Returns the element at the specified offset.
     *
     * This method is required by the SPL interface `ArrayAccess`.
     * It is implicitly called when you use something like `$value = $model[$offset];`.
     *
     * @param mixed $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Sets the element at the specified offset.
     *
     * This method is required by the SPL interface `ArrayAccess`.
     * It is implicitly called when you use something like `$model[$offset] = $item;`.
     *
     * @param integer $offset the offset to set element
     * @param mixed $item the element value
     */
    public function offsetSet($offset, $item)
    {
        $this->$offset = $item;
    }

    /**
     * Sets the element value at the specified offset to null.
     *
     * This method is required by the SPL interface `ArrayAccess`.
     * It is implicitly called when you use something like `unset($model[$offset])`.
     *
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset)
    {
        $this->$offset = null;
    }
}