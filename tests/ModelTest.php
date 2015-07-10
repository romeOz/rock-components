<?php
namespace rockunit;

use rock\base\ObjectInterface;
use rock\base\ObjectTrait;
use rock\components\Model;
use rock\components\validate\ModelValidate;
use rockunit\data\Singer;
use rockunit\data\Speaker;

/**
 * @group components
 */
class ModelTest extends \PHPUnit_Framework_TestCase
{
    public function testToArray()
    {
        $object = new Post1;
        $this->assertEquals(get_object_vars($object), Model::convert($object));
        $object = new Post2;
        $this->assertEquals(get_object_vars($object), Model::convert($object));
        $object1 = new Post1;
        $object2 = new Post2;
        $this->assertEquals(
            [
                get_object_vars($object1),
                get_object_vars($object2),
            ],
            Model::convert(
                [
                    $object1,
                    $object2,
                ]
            )
        );
        $object = new Post2;

        $expected = [
            'id' => 123,
            'secret' => 's',
            '_content' => 'test',
            'length' => 4,
        ];
        $actual = Model::convert(
            $object,
            [
                $object->className() => [
                    'id', 'secret',
                    '_content' => 'content',
                    'length' => function ($post) {
                        return strlen($post->content);
                    }
                ]
            ]
        );
        $this->assertEquals($expected, $actual);
        $object = new Post3();
        $this->assertEquals(get_object_vars($object), Model::convert($object, [], false));

        $expected = [
            'id' => 33,
            'subObject' => [
                'id' => 123,
                'content' => 'test',
            ],
        ];
        $this->assertEquals($expected, Model::convert($object));
    }


    public function testFilter()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username', 'age'], 'trim'
            ],
            [
                ['email', 'username'], 'required'
            ],
            [
                'email', '!lowercase', 'removeTags'
            ],
            [
                'username', 'customFilter' => ['.']
            ],
        ];
        $model->setAttributes(['username' => 'Tom   ', 'email' => ' <b>ToM@site.com</b>   ', 'password' => 'qwerty']);
        $this->assertTrue($model->validate());
        $expected = [
            'username' => 'Tom.',
            'email' => 'tom@site.com',
            'age' => null,
            'password' => null,
        ];
        $this->assertSame($expected, $model->getAttributes([], ['rules']));
    }


    public function testValidate()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username'], 'trim'
            ],
            [
                ['email', 'username'], 'required', 'customValidate'
            ],
            [
                'email', 'length' => [20, 80, true], 'email'
            ],
            [
                'username', 'length' => function ($modal) {
                $this->assertInstanceOf(FooModal::className(), $modal);
                return [6, 20];
            }, 'regex' => ['/^[a-z\d\-\_\.]+$/i'],
                'placeholders' => ['name' => 'foo']
            ],
            [
                'age', 'int', 'messages' => ['int' => 'error']
            ],
            [
                'email', '!lowercase'
            ],
            [
                'username', 'customFilter' => ['.']
            ],
        ];
        $model->setAttributes(['username' => 'T(o)m   ', 'email' => ' ToM@site.com   ', 'password' => 'qwerty']);
        $this->assertFalse($model->validate());
        $expected = [
            'email' =>
                [
                    'e-mail must be valid',
                    'e-mail must have a length between 20 and 80',
                ],
            'username' =>
                [
                    'username must be valid',
                    'foo must have a length between 6 and 20',
                    'foo contains invalid characters',
                ],
            'age' =>
                [
                    'error',
                ],
        ];
        $this->assertSame($expected, $model->getErrors());
        $expected = [
            'username' => 'T(o)m',
            'email' => 'ToM@site.com',
            'age' => null,
            'password' => null,
        ];
        $this->assertSame($expected, $model->getAttributes([], ['rules']));
    }

    public function testValidateSkipEmpty()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username'], 'trim'
            ],
            [
                ['email', 'username'], 'required', 'customValidate'
            ],
            [
                'username', 'length' => [6, 20], 'regex' => ['/^[a-z\d\-\_\.]+$/i'],
                'placeholders' => ['name' => 'foo']
            ],
            [
                'age', 'int', 'messages' => ['int' => 'error']
            ],
            [
                'email', 'lowercase'
            ],
            [
                'username', 'customFilter' => ['.']
            ],
        ];
        $model->setAttributes(['username' => '', 'email' => '', 'password' => '', 'age' => '']);
        $this->assertFalse($model->validate());
        $expected = [
            'email' =>
                [
                    'e-mail must not be empty',
                ],
            'username' =>
                [
                    'username must not be empty',
                ],
            'age' => ['error']
        ];
        $this->assertSame($expected, $model->getErrors());
        $expected = [
            'username' => '',
            'email' => '',
            'age' => '',
            'password' => null,
        ];
        $this->assertSame($expected, $model->getAttributes([], ['rules']));
    }

    public function testCustomMessage()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['age'], 'customValidate', 'length' => [6, 20],
                'messages' => ['length' => 'error length']
            ],
        ];
        $model->setAttributes(['age' => '25']);
        $this->assertFalse($model->validate());
        $expected = [
            'age' =>
                [
                    'value must be valid',
                    'error length',
                ],
        ];
        $this->assertSame($expected, $model->errors);
    }

    /**
     * @expectedException \rock\components\ModelException
     */
    public function testFilterThrowExceptionArgumentsMustBeArray()
    {
        $model = new FooModal();
        $model->rules = [
            [
                'email', '!lowercase' => 'exception'
            ],
        ];
        $model->setAttributes(['username' => 'Tom   ', 'email' => ' ToM@site.com   ', 'password' => 'qwerty']);
        $model->validate();
    }

    /**
     * @expectedException \rock\components\ModelException
     */
    public function testThrowExceptionUnknownRule()
    {
        $model = new FooModal();
        $model->rules = [
            [
                'email', 'is_int'
            ],
        ];
        $model->setAttributes(['username' => 'Tom   ', 'email' => ' ToM@site.com   ', 'password' => 'qwerty']);
        $model->validate();
    }

    public function testScenario()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username'], 'required', 'customValidate', 'scenarios' => ['baz']
            ],
            [
                'username', 'length' => [6, 20], 'regex' => ['/^[a-z\d\-\_\.]+$/i'],
                'placeholders' => ['name' => 'foo'], 'scenarios' => 'bar'
            ],

        ];
        $model->setAttributes(['username' => 'Tom']);
        $model->scenario = 'bar';
        $this->assertFalse($model->validate());
        $expected = [
            'username' =>
                [
                    'foo must have a length between 6 and 20',
                ],
        ];
        $this->assertSame($expected, $model->getErrors());


        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username'], 'required', 'customValidate', 'scenarios' => 'baz'
            ],
            [
                'username', 'length' => [6, 20], 'regex' => ['/^[a-z\d\-\_\.]+$/i'],
                'placeholders' => ['name' => 'foo'], 'scenarios' => 'bar'
            ],

        ];
        $model->setAttributes(['username' => 'Tom']);
        $model->scenario = 'bar';
        $this->assertFalse($model->validate());
        $expected = [
            'username' =>
                [
                    'foo must have a length between 6 and 20',
                ],
        ];
        $this->assertSame($expected, $model->getErrors());
    }

    public function testOneRule()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username', 'age'], 'required', 'one'
            ],
            [
                'username', 'length' => [6, 20], 'regex' => ['/^[a-z\d\-\_\.]+$/i']
            ],

        ];
        $model->setAttributes(['username' => 'Tom']);
        $this->assertFalse($model->validate());
        $expected = [
            'email' =>
                [
                    'e-mail must not be empty',
                ],
        ];
        $this->assertSame($expected, $model->getErrors());
    }

    public function testOneRuleByAttribute()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username', 'age'], 'required', 'one' => 'email'
            ],
            [
                'username', 'length' => [6, 20], 'regex' => ['/^[a-z\d\-\_\.]+$/i']
            ],

        ];
        $model->setAttributes(['username' => 'Tom']);
        $this->assertFalse($model->validate());
        $expected = [
            'email' =>
                [
                    'e-mail must not be empty',
                ],
        ];
        $this->assertSame($expected, $model->getErrors());

    }

    public function testWhen()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username'], 'required', 'when' => ['length' => [6, 20], function ($input, $attributeName) use ($model) {
                if (!preg_match('/^[a-z\\d\-\_\.]+$/i', $input)) {
                    $model->addError($attributeName, 'err');
                    return false;
                }
                return true;
            }]
            ],
        ];
        $model->setAttributes(['username' => 'Tom', 'email' => 'tom@site.com']);
        $this->assertFalse($model->validate());
        $expected = [
            'email' => [
                'err'
            ],
            'username' =>
                [
                    'username must have a length between 6 and 20',
                ],
        ];
        $this->assertSame($expected, $model->getErrors());
    }

    public function testIsAttributeRequired()
    {
        $model = new FooModal();
        $model->rules = [
            [
                ['email', 'username', 'age'], 'trim'
            ],
            [
                ['email', 'username'], 'required'
            ],
            [
                'email', 'mb_strtolower' => ['utf-8'], 'removeTags'
            ],
            [
                'username', 'customFilter' => ['.']
            ],
        ];
        $model->setAttributes(['username' => 'Tom   ', 'email' => ' <b>ToM@site.com</b>   ', 'password' => 'qwerty']);

        $this->assertTrue($model->isAttributeRequired('username'));
        $this->assertFalse($model->isAttributeRequired('age'));
    }

    public function testGetAttributeLabel()
    {
        $speaker = new Speaker();
        $this->assertEquals('First Name', $speaker->getAttributeLabel('firstName'));
        $this->assertEquals('This is the custom label', $speaker->getAttributeLabel('customLabel'));
        $this->assertEquals('Underscore Style', $speaker->getAttributeLabel('underscore_style'));
    }

    public function testGetAttributes()
    {
        $speaker = new Speaker();
        $speaker->firstName = 'Qiang';
        $speaker->lastName = 'Xue';
        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => 'Xue',
            'customLabel' => null,
            'underscore_style' => null,
        ], $speaker->getAttributes());
        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => 'Xue',
        ], $speaker->getAttributes(['firstName', 'lastName']));
        $this->assertEquals([
            'firstName' => 'Qiang',
            'lastName' => 'Xue',
        ], $speaker->getAttributes([], ['customLabel', 'underscore_style']));
        $this->assertEquals([
            'firstName' => 'Qiang',
        ], $speaker->getAttributes(['firstName', 'lastName'], ['lastName', 'customLabel', 'underscore_style']));
    }

    public function testErrors()
    {
        $speaker = new Speaker();
        $this->assertEmpty($speaker->getErrors());
        $this->assertEmpty($speaker->getErrors('firstName'));
        $this->assertEmpty($speaker->getFirstErrors());
        $this->assertFalse($speaker->hasErrors());
        $this->assertFalse($speaker->hasErrors('firstName'));
        $speaker->addError('firstName', 'Something is wrong!');
        $this->assertEquals(['firstName' => ['Something is wrong!']], $speaker->getErrors());
        $this->assertEquals(['Something is wrong!'], $speaker->getErrors('firstName'));
        $speaker->addError('firstName', 'Totally wrong!');
        $this->assertEquals(['firstName' => ['Something is wrong!', 'Totally wrong!']], $speaker->getErrors());
        $this->assertEquals(['Something is wrong!', 'Totally wrong!'], $speaker->getErrors('firstName'));
        $this->assertTrue($speaker->hasErrors());
        $this->assertTrue($speaker->hasErrors('firstName'));
        $this->assertFalse($speaker->hasErrors('lastName'));
        $this->assertEquals(['firstName' => 'Something is wrong!'], $speaker->getFirstErrors());
        $this->assertEquals('Something is wrong!', $speaker->getFirstError('firstName'));
        $this->assertNull($speaker->getFirstError('lastName'));
        $speaker->addError('lastName', 'Another one!');
        $this->assertEquals([
            'firstName' => [
                'Something is wrong!',
                'Totally wrong!',
            ],
            'lastName' => ['Another one!'],
        ], $speaker->getErrors());
        $speaker->clearErrors('firstName');
        $this->assertEquals([
            'lastName' => ['Another one!'],
        ], $speaker->getErrors());
        $speaker->clearErrors();
        $this->assertEmpty($speaker->getErrors());
        $this->assertFalse($speaker->hasErrors());
    }

    public function testAddErrors()
    {
        $singer = new Singer();
        $errors = ['firstName' => ['Something is wrong!']];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);
        $singer->clearErrors();
        $singer->addErrors(['firstName' => 'Something is wrong!']);
        $this->assertEquals($singer->getErrors(), ['firstName' => ['Something is wrong!']]);
        $singer->clearErrors();
        $errors = ['firstName' => ['Something is wrong!', 'Totally wrong!']];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);
        $singer->clearErrors();
        $errors = [
            'firstName' => ['Something is wrong!'],
            'lastName' => ['Another one!']
        ];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);
        $singer->clearErrors();
        $errors = [
            'firstName' => ['Something is wrong!', 'Totally wrong!'],
            'lastName' => ['Another one!']
        ];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);
        $singer->clearErrors();
        $errors = [
            'firstName' => ['Something is wrong!', 'Totally wrong!'],
            'lastName' => ['Another one!', 'Totally wrong!']
        ];
        $singer->addErrors($errors);
        $this->assertEquals($singer->getErrors(), $errors);
    }
}

class FooModal extends Model
{
    public $rules;
    public $username;
    public $email;
    public $age;
    public $password;

    public function init()
    {
        parent::init();
        $this->validate = new ModelValidate();
    }


    public function rules()
    {
        return $this->rules;
    }

    public function safeAttributes()
    {
        return ['username', 'email', 'age'];
    }

    public function attributeLabels()
    {
        return ['email' => 'e-mail', 'username' => 'username'];
    }


    public function customFilter($input = '', $attributeName, $punctuation = '')
    {
        if (!$this->hasErrors()) {
            $this->$attributeName = $input . $punctuation;
        }
    }

    public function customValidate($input = '', $attributeName)
    {
        if ($input === '') {
            return true;
        }
        $placeholders = ['name' => 'value'];
        if (is_string($input)) {
            if (($label = $this->attributeLabels()) && isset($label[$attributeName])) {
                $placeholders['name'] = $label[$attributeName];
            }

            $this->addError($attributeName, "{$placeholders['name']} must be valid");
            return false;
        }
        return true;
    }
}

class Post1
{
    public $id = 23;
    public $title = 'tt';
}

class Post2 implements ObjectInterface
{
    use ObjectTrait;

    public $id = 123;
    public $content = 'test';
    private $secret = 's';

    public function getSecret()
    {
        return $this->secret;
    }
}

class Post3 implements ObjectInterface
{
    use ObjectTrait {
        ObjectTrait::__construct as parentConstruct;
    }

    public $id = 33;
    public $subObject;

    public function __construct(array $configs = [])
    {
        $this->parentConstruct($configs);
        $this->subObject = new Post2();
    }
}