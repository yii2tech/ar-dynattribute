<?php

namespace yii2tech\tests\unit\ar\dynattribute\data;

use yii\db\ActiveRecord;
use yii2tech\ar\dynattribute\DynamicAttributeBehavior;

/**
 * @property integer $id
 * @property string $name
 * @property string $data
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
            ['groupIds', 'safe'],
        ];
    }
}