<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\components;


use Valitron\Validator;

abstract class Form
{
    protected $errors = [];
    protected $errorCode = 0;

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * @param $message
     */
    public function setErrors($message)
    {
        $this->errors[] = $message;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param $code
     */
    public function setErrorCode($code)
    {
        $this->errorCode = $code;
    }

    /**
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function rules()
    {
        return [];
    }

    public function attributes()
    {
        $class = new \ReflectionClass($this);
        $names = [];
        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    public function getAttributes($names = null)
    {
        $values = [];
        if ($names === null) {
            $names = $this->attributes();
        }
        foreach ($names as $name) {
            $values[$name] = $this->$name;
        }
        return $values;
    }

    public function setAttributes($attributes)
    {
        foreach ($attributes as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
    }

    public function validate($rules = [])
    {
        $validator = new Validator($this->getAttributes());
        if (empty($rules)) {
            $rules = $this->rules();
        }
        $validator->rules($rules);
        if ($validator->validate()) {
            return true;
        } else {
            foreach ($validator->errors() as $errors) {
                $this->errors = array_merge($this->errors, $errors);
            }
            return false;
        }
    }
}