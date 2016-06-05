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
    if (!file_exists($path)) {
        $stdErr = new \SR\Console\Std\StdErr();
        $stdErr->writeLine('Could not locate required deploy file include: %s.', $path);

        exit(255);
    }

    require_once $path;
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
