<?php
/******************************************************************************
 * An implementation of the "Formlets"-abstraction in PHP.
 * Copyright (c) 2014 Richard Klees <richard.klees@rwth-aachen.de>
 *
 * This software is licensed under The MIT License. You should have received 
 * a copy of the along with the code.
 */

use Lechimp\Formlets\Internal\Values as V;

class FunctionValueResultsTest extends PHPUnit_Framework_TestCase {
    /**
     * @dataProvider functions_and_args
     */
    public function testFunctionResult($fun, $args) {
        $fn = V::fn($fun);
        $res1 = call_user_func_array($fun, $args);
        $tmp = $fn;
        for ($i = 0; $i < $fn->arity(); ++$i) {
            $tmp = $tmp->apply(V::val($args[$i]));
        }
        $res2 = $tmp->get();
        $this->assertEquals($res1, $res2);
    
    }

    public function functions_and_args() {
        $intval = function($a) { return intval($a); };
        $explode = function($a, $b) { return explode($a, $b); };
        return array
            ( array($intval, array("12"))
            , array($intval, array("122123"))
            , array($intval, array("45689"))
            , array($explode, array(" ", "Hello World"))
            , array($explode, array(";", "1;2"))
            , array($explode, array("-", "2015-01-02"))
            );
    }
}

?>
