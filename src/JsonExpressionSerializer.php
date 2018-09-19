<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\dynattribute;

use yii\base\BaseObject;
use yii\db\JsonExpression;

/**
 * JsonExpressionSerializer serializes data into [[JsonExpression]] instance.
 * This serializer should be used in case your database supports JSON column type.
 *
 * @see JsonExpression
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0.2
 */
class JsonExpressionSerializer extends BaseObject implements SerializerInterface
{
    /**
     * @var string|null Type of JSON, expression should be casted to. Defaults to `null`, meaning
     * no explicit casting will be performed.
     * @see JsonExpression::$type
     */
    public $type;


    /**
     * {@inheritdoc}
     */
    public function serialize($value)
    {
        return new JsonExpression($value, $this->type);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($value)
    {
        if ($value instanceof JsonExpression) {
            return $value->getValue();
        }

        return $value;
    }
}