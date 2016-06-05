<?php

/*
 * This file is part of the `src-run/vermicious-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 * (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

includeDeployFile('/lib/stub/recipe-common.php');

// shared files
set('shared_files', []);

// writable dirs
set('writable_dirs', []);

// assets
set('assets', []);
set('dump_assets', false);

// Environment vars
env('env_vars', '');
env('env', 'prod');

// define clear extra front-controllers task
task('deploy:clear_controllers', getDeployTask('cleanSilexFrontControllers'))
    ->desc('Clear extra front-controllers')
    ->isPrivate();

// release task
task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:clear_controllers',
    'deploy:create_cache_dir',
    'deploy:shared',
    'deploy:shared:fixtures',
    'deploy:assets',
    'deploy:vendors',
    'deploy:writable',
    'deploy:symlink',
    'cleanup',
    'service:php-fpm:reload',
    'service:memcached:restart',
    'release:current',
])->desc('Deploy Silex project');

// release task alias
task('release:deploy', [
    'deploy'
])->desc('Deploy Silex project');

/* EOF */
