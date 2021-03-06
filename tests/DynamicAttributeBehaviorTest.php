<?php

namespace yii2tech\tests\unit\ar\dynattribute;

use yii2tech\ar\dynattribute\CallbackSerializer;
use yii2tech\ar\dynattribute\DynamicAttributeBehavior;
use yii2tech\ar\dynattribute\JsonSerializer;
use yii2tech\ar\dynattribute\PhpSerializer;
use yii2tech\tests\unit\ar\dynattribute\data\Item;

class DynamicAttributeBehaviorTest extends TestCase
{
    public function testSetupSerializer()
    {
        $behavior = new DynamicAttributeBehavior();

        $defaultSerializer = $behavior->getSerializer();
        $this->assertTrue($defaultSerializer instanceof JsonSerializer);

        $serializer = new PhpSerializer();
        $behavior->setSerializer($serializer);
        $this->assertSame($serializer, $behavior->getSerializer());

        $behavior->setSerializer([
            'serialize' => 'serialize',
            'unserialize' => 'unserialize',
        ]);
        $serializer = $behavior->getSerializer();
        $this->assertTrue($serializer instanceof CallbackSerializer);
    }

    public function testSetupDynamicAttributes()
    {
        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();

        $model->hasComment = true;
        $model->commentCount = 10;

        $expectedAttributes = [
            'hasComment' => true,
            'commentCount' => 10,
        ];
        $this->assertEquals($expectedAttributes, $behavior->getDynamicAttributes());

        $attributes = [
            'hasComment' => false,
            'commentCount' => 99,
        ];
        $behavior->setDynamicAttributes($attributes);
        $this->assertEquals($attributes, $behavior->getDynamicAttributes());
    }

    /**
     * @depends testSetupDynamicAttributes
     */
    public function testGetAttributeDefaultValue()
    {
        $model = new Item();

        $this->assertFalse($model->hasComment);
        $this->assertSame(0, $model->commentCount);
    }

    /**
     * @depends testSetupDynamicAttributes
     */
    public function testNullDefaultValue()
    {
        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();
        $behavior->dynamicAttributeDefaults = [
            'commentCount' => null,
        ];
        $this->assertNull($model->commentCount);

        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();
        $behavior->dynamicAttributeDefaults = [
            'commentCount' => null,
        ];
        $model->commentCount = 15;
        $this->assertEquals(15, $model->commentCount);
    }

    /**
     * @depends testSetupSerializer
     * @depends testSetupDynamicAttributes
     */
    public function testInsertDynamicAttributes()
    {
        $model = new Item();
        $model->name = 'test';

        $model->hasComment = true;
        $model->commentCount = 10;

        $model->save(false);

        $refreshedModel = Item::findOne($model->getPrimaryKey());

        $this->assertNotEmpty($refreshedModel->data);

        $this->assertEquals($model->hasComment, $refreshedModel->hasComment);
        $this->assertEquals($model->commentCount, $refreshedModel->commentCount);
    }

    /**
     * @depends testInsertDynamicAttributes
     */
    public function testUpdateDynamicAttributes()
    {
        $model = new Item();
        $model->name = 'test';
        $model->hasComment = true;
        $model->commentCount = 10;
        $model->save(false);

        $existingModel = Item::findOne($model->getPrimaryKey());

        $existingModel->hasComment = false;
        $existingModel->commentCount = 99;
        $existingModel->save(false);

        $refreshedModel = Item::findOne($model->getPrimaryKey());

        $this->assertEquals($existingModel->hasComment, $refreshedModel->hasComment);
        $this->assertEquals($existingModel->commentCount, $refreshedModel->commentCount);
    }

    /**
     * @depends testInsertDynamicAttributes
     */
    public function testSkipIfNotInitialized()
    {
        $model = new Item();
        $model->name = 'test';
        $model->save(false);

        $refreshedModel = Item::findOne($model->getPrimaryKey());

        $this->assertEmpty($refreshedModel->data);
    }

    /**
     * @depends testSetupDynamicAttributes
     */
    public function testSetRandomDynamicAttribute()
    {
        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();

        $behavior->allowRandomDynamicAttribute = true;
        $model->unexistingProperty = 'some';
        $this->assertEquals('some', $model->unexistingProperty);

        $behavior->allowRandomDynamicAttribute = false;
        $this->expectException('yii\base\UnknownPropertyException');
        $model->anotherUnexistingProperty = 'foo';
    }

    /**
     * @depends testInsertDynamicAttributes
     */
    public function testSaveDynamicAttributeDefaults()
    {
        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();
        $behavior->saveDynamicAttributeDefaults = false;

        $model->commentCount = 10;
        $model->save(false);
        $this->assertEquals('{"commentCount":10}', $model->data);

        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();
        $behavior->saveDynamicAttributeDefaults = true;

        $model->commentCount = 10;
        $model->save(false);
        $this->assertEquals('{"commentCount":10,"hasComment":false}', $model->data);
    }

    /**
     * @depends testSetRandomDynamicAttribute
     * @depends testUpdateDynamicAttributes
     * @depends testSaveDynamicAttributeDefaults
     */
    public function testDynamicAttributeSaveFilter()
    {
        $model = new Item();
        $behavior = $model->getDynamicAttributeBehavior();
        $behavior->allowRandomDynamicAttribute = true;
        $behavior->saveDynamicAttributeDefaults = false;

        $model->commentCount = 12;
        $model->some = 'foo';
        $behavior->dynamicAttributeSaveFilter = true;
        $model->save(false);
        $this->assertEquals('{"commentCount":12}', $model->data);

        $model->some = 'foo';
        $behavior->dynamicAttributeSaveFilter = function ($attributes) {
            $attributes['save'] = $attributes['some'];
            unset($attributes['commentCount']);
            unset($attributes['some']);
            return $attributes;
        };
        $model->save(false);
        $this->assertEquals('{"save":"foo"}', $model->data);
    }
}