ActiveRecord Position Extension for Yii2
========================================

This extension provides dynamic ActiveRecord attributes stored into the single field in serialized state.

For license information check the [LICENSE](LICENSE.md)-file.

[![Latest Stable Version](https://poser.pugx.org/yii2tech/ar-dynattribute/v/stable.png)](https://packagist.org/packages/yii2tech/ar-dynattribute)
[![Total Downloads](https://poser.pugx.org/yii2tech/ar-dynattribute/downloads.png)](https://packagist.org/packages/yii2tech/ar-dynattribute)
[![Build Status](https://travis-ci.org/yii2tech/ar-dynattribute.svg?branch=master)](https://travis-ci.org/yii2tech/ar-dynattribute)


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist yii2tech/ar-dynattribute
```

or add

```json
"yii2tech/ar-dynattribute": "*"
```

to the require section of your composer.json.


Usage
-----

This extension provides dynamic ActiveRecord attributes stored into the single field in serialized state.
For example: imagine we create a web site, where logged in user may customize its appearance, like changing
color schema or enable/disable sidebar and so on. In order to make this customization persistent all user's
choices should be stored into the database. In general each view setting should have its own column in the
'user' table. However, this is not very practical in case your application is under development and new
settings appear rapidly. Thus it make sense to use single text field, which will store all chosen view
parameters in the serialized string. If new option introduced there will no necessity to change 'user' table
schema.
Migration for the 'user' table creation may look like following:

```php
class m??????_??????_create_user extends \yii\db\Migration
{
    public function up()
    {
        $this->createTable('User', [
            'id' => $this->primaryKey(),
            'username' => $this->string()->notNull(),
            'email' => $this->string()->notNull(),
            'passwordHash' => $this->string()->notNull(),
            // ...
            'viewParams' => $this->text(), // field, which stores view parameters in serialized state
        ]);
    }

    public function down()
    {
        $this->dropTable('User');
    }
}
```

**Heads up!** In general such data storage approach is a **bad** practice and is not recommended to be used.
Its main drawback is inability to use dynamic attributes in condition for the search query.
It is acceptable only for the attributes, which are directly set and read for single record only, and never
used for the filter queries.

This extension provides [[\yii2tech\ar\dynattribute\DynamicAttributeBehavior]] ActiveRecord behavior for
the dynamic attributes support.
For example:

```php
use yii\db\ActiveRecord;
use yii2tech\ar\dynattribute\DynamicAttributeBehavior;

class User extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'dynamicAttribute' => [
                'class' => DynamicAttributeBehavior::className(),
                'sourceAttribute' => 'viewParams', // field to store serialized attributes
                'dynamicAttributeDefaults' => [ // default values for the dynamic attributes
                    'bgColor' => 'green',
                    'showSidebar' => true,
                ],
            ],
        ];
    }

    public static function tableName()
    {
        return 'User';
    }

    // ...
}
```

Once being attached [[\yii2tech\ar\dynattribute\DynamicAttributeBehavior]] allows its owner to operate
dynamic attributes just as regular one. On model save they will be serialized and stored into the holding
field. After record is fetched from database the first attempt to read the dynamic attributes will unserialize
them and prepare for the usage.
For example:

```php
$model = new User();
// ...
$model->bgColor = 'red';
$model->showSidebar = false;
$model->save(); // 'bgColor' and 'showSidebar' are serialized and stored at 'viewParams'
echo $model->viewParams; // outputs: '{"bgColor": "red", "showSidebar": false}'

$refreshedModel = User::findOne($model->getPrimaryKey());
echo $refreshedModel->bgColor; // outputs 'red'
echo $refreshedModel->showSidebar; // outputs 'false'
```

You may use dynamic attributes as the regular ActiveRecord attributes. For example: you may
specify the validation rules for them and obtain their values via web form.

> Note: keep in mind that dynamic attributes do not correspond to ActiveRecord entity fields, thus
  some particular ActiveRecord methods like `updateAttributes()` will not work for them.


## Default values setup <span id="default-values-setup"></span>

As you may note from above example, you can provide a default values for the dynamic attributes
via [[\yii2tech\ar\dynattribute\DynamicAttributeBehavior::dynamicAttributeDefaults]].
Thus once you need extra dynamic attribute for your model you can just update the `dynamicAttributeDefaults`
list with corresponding value, without necessity to perform any updates on your database.

```
class User extends ActiveRecord
{
    public function behaviors()
    {
        return [
            'dynamicAttribute' => [
                'class' => DynamicAttributeBehavior::className(),
                'sourceAttribute' => 'viewParams',
                'dynamicAttributeDefaults' => [
                    'bgColor' => 'green',
                    'showSidebar' => true,
                    'fontColor' => 'black', // newly added attribute
                ],
            ],
        ];
    }

    // ...
}

$newModel = new User();
echo $newModel->bgColor; // outputs 'green'
echo $newModel->showSidebar; // outputs 'true'

$oldModel = User::find()->orderBy(['id' => SORT_ASC])->limit(1)->one();
echo $oldModel->viewParams; // outputs: '{"bgColor": "red", "showSidebar": false}'
echo $oldModel->fontColor; // outputs: 'black'
```


## Restrict dynamic attribute list <span id="restrict-dynamic-attribute-list"></span>

Setup of the dynamic attribute default values not only useful, but in general is necessary.
This list puts a restriction on the possible dynamic attribute names. Only attributes, which
have default value specified can be set or read from the model. This prevents the possible mistakes
caused by typos in the code.
For example:

```php
$newModel = new User();
$newModel->bgColor = 'blue'; // works fine
$newModel->unExistingAttribute = 10; // throws an exception!
```

However sometimes there is necessity of storage list of attributes, which can not be predicted.
For example, saving response fields from some external service.
In this case you can disable check performed on attribute setter using
[[\yii2tech\ar\dynattribute\DynamicAttributeBehavior::allowRandomDynamicAttribute]].
If it is set to `true` you will be able to setup any dynamic attribute no matter declared or not
at `dynamicAttributeDefaults`.

> Note: you can also use [[\yii2tech\ar\dynattribute\DynamicAttributeBehavior::setDynamicAttributes()]] method
  to bypass naming restriction. This method will set all provided attributes without any checks.


## Serializer setup <span id="serializer-setup"></span>

By default [[\yii2tech\ar\dynattribute\DynamicAttributeBehavior]] saves the dynamic attribute in JSON
format. However, you may setup another serializer for them via [[\yii2tech\ar\dynattribute\DynamicAttributeBehavior::serializer]].
The following serializers are available withing this extension:

 - [[\yii2tech\ar\dynattribute\JsonSerializer]] - stores data in JSON format
 - [[\yii2tech\ar\dynattribute\PhpSerializer]] - stores data using PHP `serialize()`/`unserialize()` functions
 - [[\yii2tech\ar\dynattribute\CallbackSerializer]] - stores data via custom serialize PHP callback.

Please refer to the particular serializer class for more details.
