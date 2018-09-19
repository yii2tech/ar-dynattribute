<?php

namespace yii2tech\tests\unit\ar\dynattribute;

use yii\db\JsonExpression;
use yii2tech\ar\dynattribute\JsonExpressionSerializer;

class JsonExpressionSerializerTest extends TestCase
{
    public function testSerialize()
    {
        $serializer = new JsonExpressionSerializer();

        $value = ['name' => 'value'];
        $serializedValue = $serializer->serialize(['name' => 'value']);
        $this->assertTrue($serializedValue instanceof JsonExpression);
        $this->assertEquals($value, $serializedValue->getValue());
    }

    public function testUnserialize()
    {
        $serializer = new JsonExpressionSerializer();

        $value = ['name' => 'value'];
        $serializedValue = new JsonExpression($value);

        $this->assertEquals($value, $serializer->unserialize($serializedValue));
    }
}