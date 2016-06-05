<?php

/*
 * This file is part of the `src-run/vermicious-symfony-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\Deployer\Task;

use SR\Console\Std\StdErr;
use SR\Reflection\Inspect;
use SR\Utility\ClassInspect;

/**
 * Class TaskClosures.
 */
final class TaskClosures
{
    /**
     * @param string $method
     * @param string $class
     *
     * @return \Closure
     */
    public static function get($method, $class = 'SR\Deployer\Task\TaskClosures')
    {
        if (($e = static::isValidClass($class)) instanceof \Closure) {
            return $e;
        }

        if (($e = static::isValidMethod($method, $class)) instanceof \Closure) {
            return $e;
        }

        return static::getClosure($method, $class);
    }

    /**
     * @param string $class
     *
     * @return bool|\Closure
     */
    private static function isValidClass($class)
    {
        if (ClassInspect::isClass($class)) {
            return true;
        }
        
        return function () use ($class) {
            $stdErr = new StdErr();
            $stdErr->writeLine('[WARNING] Requested task class "%s" could not be found.', $class);
        };
    }

    /**
     * @param string $method
     * @param string $class
     *
     * @return bool|\Closure
     */
    private static function isValidMethod($method, $class)
    {
        $inspect = Inspect::thisClass($class);

        if($inspect->hasMethod($method) && $inspect->getMethod($method)->visibilityPublic()) {
            return true;
        }

        return function () use ($method, $class) {
            $stdErr = new StdErr();
            $stdErr->writeLine('[WARNING] Request task "%s::%s" could not be found.', $class, $method);
        };
    }

    /**
     * @param string $method
     * @param string $class
     *
     * @return \Closure
     */
    private static function getClosure($method, $class)
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new $class();
        }

        return function () use ($instance, $method) {
            $instance->{$method}();
        };
    }
}

/* EOF */
