<?php

/******************************************************************************
 * Copyright (c) 2014 Richard Klees <richard.klees@rwth-aachen.de>
 */

require_once("checking.php");
require_once("values.php");
require_once("html.php");

/******************************************************************************
 * Turn a value to two dictionaries:
 *  - one contains the original values as inputted by the user.
 *  - one contains the origins and the errors on those values.
 */
class RenderDict {
    private $_values; // array
    private $_errors; // array
    private $_empty; // bool 

    public function isEmpty() {
        return $this->_empty;
    }

    public function value($name) {
        if ($this->valueExists($name))
            return $this->_values[$name];
        return null;
    }

    public function valueExists($name) {
        return array_key_exists($name, $this->_values);
    }

    public function errors($name) {
        if (array_key_exists($name, $this->_errors))
            return $this->_errors[$name];
        return null;
    }

    public function __construct($inp, Value $value, $_empty = false) {
        guardIsBool($_empty);
        $res = self::computeFrom($value);
        $this->_values = $inp; 
        $this->_errors = $res; 
        $this->_empty = $_empty;
    }

    private static $_emptyInst = null;

    public static function _empty() {
        // ToDo: Why does this not work?
        /*if (self::_emptyInst === null) {
            self::_emptyInst = new RenderDict(_value(0));
        }
        return self::_emptyInst;*/
        return new RenderDict(array(), _value(0), true);
    }  

    public static function computeFrom(Value $value) {
        $errors = array();
        self::dispatchValue($value, $errors);
        return $errors;
    }

    protected static function dispatchValue($value, &$errors) {
        if ($value instanceof ErrorValue) {
            self::handleError($value, $errors); 
        } 
        elseif ($value instanceof FunctionValue) {
            self::handleFunction($value, $errors);
        }
        else {
            self::handleValue($value, $errors); 
        }
    }

    protected static function handleError($value, &$errors) {
        $origin = $value->origin();
        if ($origin !== null) {
            if (!array_key_exists($origin, $errors)) {
                $errors[$origin] = array();
            }
            $errors[$origin][] = $value->error();
        }
        self::dispatchValue($value->originalValue(), $errors);
    }

    protected static function handleFunction($value, &$errors) {
        foreach($value->args() as $value) {
            self::dispatchValue($value, $errors);
        }
    }

    protected static function handleValue($value, &$errors) {
    }
}

/******************************************************************************
 * Fairly simple implementation of a Builder. Can render strings and supports
 * combining of builders. A more sophisticated version could be build upon
 * HTML primitives.
 */

abstract class Builder {
    /* Returns a string. */
    abstract public function buildWithDict(RenderDict $dict);
    public function build() {
        return $this->buildWithDict(RenderDict::_empty());
    }
}

/* Builder that combines two sub builders by adding the output of the 
 * builders.
 */
class CombinedBuilder extends Builder {
    private $_l; // Builder
    private $_r; // Builder

    public function __construct(Builder $left, Builder $right) {
        $this->_l = $left;
        $this->_r = $right;
    }

    public function buildWithDict(RenderDict $dict) {
        return $this->_l->buildWithDict($dict)
                ->concat($this->_r->buildWithDict($dict));
    }
}

/* A builder that produces a constant output. */
class ConstBuilder extends Builder {
    private $_content; // string

    public function __construct($content) {
        $this->_content = literal($content);
    }

    public function buildWithDict(RenderDict $dict) {
        return $this->_content;
    }
}

class TagBuilder extends Builder {
    private $_tag_name; // string
    private $_attributes_function; // FunctionValue 
    private $_content_function; // FunctionValue 

    public function __construct( $tag_name
                               , FunctionValue $attributes_function
                               , FunctionValue $content_function
                               ) {
        guardIsString($tag_name);
        $this->_tag_name = $tag_name;
        $this->_attributes_function = $attributes_function;
        $this->_content_function = $content_function;
    }

    public function buildWithDict(RenderDict $dict) {
        $d = _value($dict);
        $attributes = $this->_attributes_function->apply($d)->get();
        $content = $this->_content_function->apply($d)->get();
        return tag($this->_tag_name, $attributes, $content); 
    }
}
    
/* A builder that calls 'build' from another object to produce its output. */
class CallbackBuilder extends Builder {
    private $_call_object; // callable
    private $_name; // string

    /* Construct with object to call and an array of arguments to be passed
     * to said óbjects build method.
     */
    public function __construct($call_object, $name) {
        guardIsObject($call_object);
        if ($name !== null)
            guardIsString($name);
        $this->_call_object = $call_object;
        $this->_name= $name;
    }

    public function buildWithDict(RenderDict $dict) {
        $res = $this->_call_object->getHTMLEntity($dict, $this->_name);
        guardIsHTMLEntity($res);
        return $res; 
    }
}

?>