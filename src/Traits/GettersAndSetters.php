<?php

namespace Sunnysideup\UpgradeSilverstripe\Traits;

use Sunnysideup\UpgradeSilverstripe\Interfaces\ModuleUpgraderInterface;
use Sunnysideup\UpgradeSilverstripe\ModuleUpgrader;

trait GettersAndSetters
{
    /**
     * creates magic getters and setters
     * if you call $this->getFooBar() then it will get the variable FooBar even if the method
     * getFooBar does not exist.
     *
     * if you call $this->setFooBar('hello') then it will set the variable FooBar even if the method
     * setFooBar does not exist.
     *
     * See: http://php.net/manual/en/language.oop5.overloading.php#object.call
     *
     * @param  string   $function name of the function
     * @param  array    $args     parameters provided to the getter / setter
     */
    public function __call($function, $args)
    {
        $getOrSet = substr($function, 0, 3);
        if ($getOrSet === 'set' || $getOrSet === 'get') {
            $var = lcfirst(ltrim($function, $getOrSet));
            if (property_exists($this, $var)) {
                if ($getOrSet === 'get') {
                    return $this->{$var};
                } elseif ($getOrSet === 'set') {
                    $this->{$var} = $args[0];

                    return $this;
                }
            } else {
                user_error(
                    'Fatal error: can not get/set variable in ModuleUpgraderBaseWithVariables::' . $var,
                    E_USER_ERROR
                );
            }
        } else {
            if ($function == 'mu' && $this instanceof ModuleUpgrader) {
                return $this;
            }
            user_error(
                'Fatal error: Call to undefined method ModuleUpgraderBaseWithVariables::' . $function . '()',
                E_USER_ERROR
            );
        }
    }
}
