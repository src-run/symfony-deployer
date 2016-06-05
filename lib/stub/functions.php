<?php

/*
 * This file is part of the `src-run/vermicious-symfony-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

/**
 * @param string $path
 */
function requireDeployInclude($path)
{
    if (file_exists($include = __DIR__ . '/../../' . $path)) {
        require_once $include;
        return;
    } elseif (file_exists($include = __DIR__ . '/../../../../../' . $path)) {
        require_once $include;
        return;
    }

    $stdErr = new \SR\Console\Std\StdErr();
    $stdErr->writeLine('Could not locate required deploy file include "%s(/../../|/../../../../../)%s".', __DIR__, $path);

    exit(255);
}

/**
 * @param string $path
 */
function requireDeployVendorInclude($path)
{
    if (file_exists($include = __DIR__ . '/../../vendor/' . $path)) {
        require_once $include;
        return;
    } elseif (file_exists($include = __DIR__ . '/../../../../' . $path)) {
        require_once $include;
        return;
    }

    $stdErr = new \SR\Console\Std\StdErr();
    $stdErr->writeLine('Could not locate required deploy vendor file include "%s(/../../vendor/|/../../../../)%s".', __DIR__, $path);

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
