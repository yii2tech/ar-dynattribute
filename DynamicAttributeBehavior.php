<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\ar\dynattribute;

use yii\base\Behavior;
use yii\base\InvalidParamException;
use yii\base\UnknownPropertyException;
use yii\db\BaseActiveRecord;
use yii\di\Instance;

/**
 * DynamicAttributeBehavior
 *
 * @property BaseActiveRecord $owner
 * @property string|array|SerializerInterface $serializer serializer instance or its configuration.
 * @property array $dynamicAttributes dynamic attributes in format: name => value.
 * @property boolean $isDynamicAttributeInitialized whether the dynamic attributes have been initialized or not.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class DynamicAttributeBehavior extends Behavior
{
    /**
     * @var string name of the owner attribute, which stores serialized dynamic attribute values.
     */
    public $sourceAttribute = 'data';
    /**
     * @var array list of dynamic attribute default values.
     *
     * For example:
     *
     * ```php
     * [
     *     'hasComment' => false,
     *     'commentCount' => 0,
     *     'gender' => null,
     * ]
     * ```
     */
    public $dynamicAttributeDefaults = [];
    /**
     * @var boolean whether set of the attribute with the name, which is not exist neither at current [[dynamicAttributes]]
     * nor at [[$dynamicAttributeDefaults]], is allowed or not.
     * By default this option is disabled, providing the limitation of the dynamic attribute names, which can
     * be set via virtual property access or [[setDynamicAttribute()]] method.
     * If enabled dynamic attribute with any name will be allowed to be set.
     */
    public $allowRandomDynamicAttribute = false;

    /**
     * @var array dynamic attributes in format: name => value.
     */
    private $_dynamicAttributes;
    /**
     * @var string|array|SerializerInterface serializer instance or its configuration.
     * Following shortcuts are supported:
     *
     * - 'php' - use [[PhpSerializer]]
     * - 'json' - use [[JsonSerializer]]
     *
     * Using array configuration, you may omit 'class' parameter, in this case [[CallbackSerializer]] will be used.
     * For example:
     *
     * ```php
     * [
     *     'serialize' => function ($value) { return serialize($value); },
     *     'unserialize' => function ($value) { return unserialize($value); },
     * ]
     * ```
     */
    private $_serializer = 'json';


    /**
     * Returns dynamic attribute values.
     * @return array dynamic attribute values in format: name => value.
     */
    public function getDynamicAttributes()
    {
        if ($this->_dynamicAttributes === null) {
            $this->_dynamicAttributes = $this->unserializeAttributes($this->owner->{$this->sourceAttribute});
            if (!empty($this->dynamicAttributeDefaults)) {
                $this->_dynamicAttributes = array_merge($this->dynamicAttributeDefaults, $this->_dynamicAttributes);
            }
        }
        return $this->_dynamicAttributes;
    }

    /**
     * Sets dynamic attribute values.
     * Note that this method ignores [[allowRandomDynamicAttribute]] option.
     * @param array $dynamicAttributes dynamic attribute values in format: name => value.
     */
    public function setDynamicAttributes($dynamicAttributes)
    {
        $this->_dynamicAttributes = $dynamicAttributes;
    }

    /**
     * Returns the value of specified dynamic attribute.
     * @param string $name attribute name.
     * @return mixed attribute value.
     */
    public function getDynamicAttribute($name)
    {
        $attributes = $this->getDynamicAttributes();
        if (!array_key_exists($name, $attributes)) {
            throw new InvalidParamException('Getting unknown dynamic attribute: ' . get_class($this->owner) . '::' . $name);
        }
        return $attributes[$name];
    }

    /**
     * Sets the value of the specified dynamic attribute.
     * @param string $name attribute name.
     * @param mixed $value attribute value.
     */
    public function setDynamicAttribute($name, $value)
    {
        $attributes = $this->getDynamicAttributes();
        if (!$this->allowRandomDynamicAttribute && !array_key_exists($name, $attributes)) {
            throw new InvalidParamException('Setting unknown dynamic attribute: ' . get_class($this->owner) . '::' . $name);
        }
        $attributes[$name] = $value;
        $this->setDynamicAttributes($attributes);
    }

    /**
     * @return SerializerInterface serializer instance
     */
    public function getSerializer()
    {
        if (!is_object($this->_serializer)) {
            $this->_serializer = $this->createSerializer($this->_serializer);
        }
        return $this->_serializer;
    }

    /**
     * @param SerializerInterface|array|string $serializer serializer to be used
     */
    public function setSerializer($serializer)
    {
        $this->_serializer = $serializer;
    }

    /**
     * Creates serializer from given configuration.
     * @param string|array $config serializer configuration.
     * @return SerializerInterface serializer instance
     */
    protected function createSerializer($config)
    {
        if (is_string($config)) {
            switch ($config) {
                case 'php':
                    $config = [
                        'class' => PhpSerializer::className()
                    ];
                    break;
                case 'json':
                    $config = [
                        'class' => JsonSerializer::className()
                    ];
                    break;
            }
        } elseif (is_array($config)) {
            if (!isset($config['class'])) {
                $config['class'] = CallbackSerializer::className();
            }
        }
        return Instance::ensure($config, 'yii2tech\ar\dynattribute\SerializerInterface');
    }

    /**
     * @return boolean whether the dynamic attributes have been initialized or not.
     */
    public function getIsDynamicAttributeInitialized()
    {
        return ($this->_dynamicAttributes !== null);
    }

    /**
     * Serializes given attributes into a string.
     * @param array $attributes attributes to be serialized in format: name => value
     * @return string serialized attributes.
     */
    protected function serializeAttributes($attributes)
    {
        ksort($attributes); // sort the data to facilitate 'dirty-attributes' AR feature
        return $this->getSerializer()->serialize($attributes);
    }

    /**
     * Restores attribute values from string.
     * @param string $source serialized data string.
     * @return array restored attributes.
     */
    protected function unserializeAttributes($source)
    {
        if (empty($source)) {
            return [];
        }
        return (array)$this->getSerializer()->unserialize($source);
    }

    // Property Access Extension:

    /**
     * PHP getter magic method.
     * This method is overridden so that dynamic attribute can be accessed like property.
     *
     * @param string $name property name
     * @throws UnknownPropertyException if the property is not defined
     * @return mixed property value
     */
    public function __get($name)
    {
        try {
            return parent::__get($name);
        } catch (UnknownPropertyException $exception) {
            $attributes = $this->getDynamicAttributes();
            if (array_key_exists($name, $attributes)) {
                return $attributes[$name];
            }
            throw $exception;
        }
    }

    /**
     * PHP setter magic method.
     * This method is overridden so that dynamic attribute can be accessed like property.
     * @param string $name property name
     * @param mixed $value property value
     * @throws UnknownPropertyException if the property is not defined
     */
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (UnknownPropertyException $exception) {
            $attributes = $this->getDynamicAttributes();
            if (!$this->allowRandomDynamicAttribute && !array_key_exists($name, $attributes)) {
                throw $exception;
            }
            $attributes[$name] = $value;
            $this->setDynamicAttributes($attributes);
        }
    }

    /**
     * Checks if a property is set, i.e. defined and not null.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `isset($object->property)`.
     *
     * Note that if the property is not defined, false will be returned.
     * @param string $name the property name or the event name
     * @return boolean whether the named property is set (not null).
     * @see http://php.net/manual/en/function.isset.php
     */
    public function __isset($name)
    {
        if (parent::__isset($name)) {
            return true;
        }
        $attributes = $this->getDynamicAttributes();
        return isset($attributes[$name]);
    }

    /**
     * Sets an object property to null.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `unset($object->property)`.
     *
     * Note that if the property is not defined, this method will do nothing.
     * If the property is read-only, it will throw an exception.
     * @param string $name the property name
     * @see http://php.net/manual/en/function.unset.php
     */
    public function __unset($name)
    {
        $attributes = $this->getDynamicAttributes();
        if (array_key_exists($name, $attributes)) {
            unset($attributes[$name]);
            $this->setDynamicAttributes($attributes);
        } else {
            parent::__unset($name);
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if (parent::canGetProperty($name, $checkVars)) {
            return true;
        }
        $attributes = $this->getDynamicAttributes();
        return isset($attributes[$name]);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if (parent::canSetProperty($name, $checkVars)) {
            return true;
        }
        $attributes = $this->getDynamicAttributes();
        return $this->allowRandomDynamicAttribute || isset($attributes[$name]);
    }

    // Events :

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
        ];
    }

    /**
     * Handles owner 'beforeInsert' and 'beforeUpdate' events, ensuring dynamic attributes are saved.
     * @param \yii\base\Event $event event instance.
     */
    public function beforeSave($event)
    {
        if (!$this->getIsDynamicAttributeInitialized()) {
            return;
        }

        $attributes = $this->getDynamicAttributes();
        $data = $this->serializeAttributes($attributes);

        $this->owner->{$this->sourceAttribute} = $data;
    }
}