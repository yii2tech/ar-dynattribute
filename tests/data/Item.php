<?php

namespace yii2tech\tests\unit\ar\dynattribute\data;

use yii\db\ActiveRecord;
use yii2tech\ar\dynattribute\DynamicAttributeBehavior;

/**
 * @property integer $id
 * @property string $name
 * @property string $data
 *
 * @property boolean $hasComment
 * @property integer $commentCount
 */
class Item extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'dynamicAttribute' => [
                'class' => DynamicAttributeBehavior::className(),
                'storageAttribute' => 'data',
                'dynamicAttributeDefaults' => [
                    'hasComment' => false,
                    'commentCount' => 0,
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'Item';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'required'],
        ];
    }

    /**
     * @return DynamicAttributeBehavior dynamic attribute behavior instance.
     */
    public function getDynamicAttributeBehavior()
    {
        return $this->getBehavior('dynamicAttribute');
    }
}