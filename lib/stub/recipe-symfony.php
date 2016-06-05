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

// Symfony shared dirs
set('shared_dirs', ['app/logs']);

// Symfony shared files
set('shared_files', ['app/config/parameters.yml']);

// Symfony writable dirs
set('writable_dirs', ['app/cache', 'app/logs']);

// Assets
set('assets', ['web/css', 'web/images', 'web/js']);
set('dump_assets', false);

// Environment vars
env('env_vars', 'SYMFONY_ENV=prod');
env('env', 'prod');

// Adding support for the Symfony3 directory structure
set('bin_dir', 'bin');
set('var_dir', 'var');

// define assetic dump
task('deploy:assetic:dump', getDeployTask('assetDump'))
    ->desc('Assetic dump');

// define cache warming task
task('deploy:cache:warmup', getDeployTask('cacheWarmup'))
    ->desc('Warm up cache');

// define database migration task
task('database:migrate', getDeployTask('databaseMigrate'))
    ->desc('Migrate database');

// define clear extra front-controllers task
task('deploy:clear_controllers', getDeployTask('cleanSymfonyFrontControllers'))
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
    'database:migrate',
    'deploy:assetic:dump',
    'deploy:cache:warmup',
    'deploy:writable',
    'deploy:symlink',
    'cleanup',
    'service:php-fpm:reload',
    'service:memcached:restart',
    'release:current',
])->desc('Deploy Symfony project');

// release task alias
task('release:deploy', [
    'deploy'
])->desc('Deploy Silex project');

/* EOF */
