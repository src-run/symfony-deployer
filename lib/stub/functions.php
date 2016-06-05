<?php

/*
 * This file is part of the `src-run/vermicious-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Deployer\Task\Context;

/**
 * @param string $path
 */
function requireDeployInclude($path)
{
    if (file_exists($include = __DIR__ . '/../../' . $path) ||
        file_exists($include = __DIR__ . '/../../../../../' . $path)) {
        require_once $include;
        return;
    }

    Context::get()->getOutput()->writeln(sprintf('Could not locate required deploy file include "%s(/../../|/../../../../../)%s".', __DIR__, $path));
    exit(255);
}

/**
 * @param string $path
 */
function requireDeployVendorInclude($path)
{
    if (file_exists($include = __DIR__ . '/../../vendor/' . $path) ||
        file_exists($include = __DIR__ . '/../../../../' . $path)) {
        require_once $include;
        return;
    }

    Context::get()->getOutput()->writeln(sprintf('Could not locate required deploy vendor file include "%s(/../../vendor/|/../../../../)%s".', __DIR__, $path));
    exit(255);
}

/**
 * @param string $path
 */
function requireDeployServerInclude($path)
{
    if (file_exists($include = __DIR__ . '/../../' . $path) ||
        file_exists($include = __DIR__ . '/../../../../../' . $path)) {
        serverList($include);
        return;
    }
    
    Context::get()->getOutput()->writeln(sprintf('Could not locate required deploy server file include "%s(/../../|/../../../../../)%s".', __DIR__, $path));
    exit(255);
}

/**
 * @param string $name
 *
 * @return Closure
 */
function getDeployTask($name)
{
    return \SR\Deployer\Task\TaskClosures::get($name);
}

/* EOF */
