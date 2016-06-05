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

includeDeployFile('/vendor/deployer/deployer/recipe/common.php');

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

// Adding support for the Symfony3 directory structure
set('bin_dir', '.');
set('var_dir', '.');

// Create cache dir
task('deploy:create_cache_dir', getDeployTask('deployCreateCacheDirectory'))
    ->desc('Create cache dir');

// define assets deploy task
task('deploy:assets', getDeployTask('deployAssets'))
    ->desc('Normalize asset timestamps');

// define php-fpm task and when to call it (after deploy and rollback)
task('service:php-fpm:reload', getDeployTask('servicePhpFpmReload'))
    ->desc('Reload php-fpm');

// define memcached task and when to call it (after deploy and rollback)
task('service:memcached:restart', getDeployTask('serviceMemcachedRestart'))
    ->desc('Restart memcached');

// define composer run (deploy:vendors)
task('deploy:vendors', getDeployTask('deployVendors'))
    ->desc('Installing vendors');

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
task('deploy:clear_controllers', getDeployTask('cleanFrontControllers'))
    ->desc('Clear extra front-controllers')
    ->isPrivate();

// define shared fixtures task
task('deploy:shared:fixtures', getDeployTask('deployFixtures'))
    ->desc('Deploying shared fixtures');

// define writable deploy task
task('deploy:writable', getDeployTask('deployWritable'))
    ->desc('Make writable dirs')
    ->setPrivate();

// define task to show current release
task('release:current', getDeployTask('releaseCurrent'))
    ->desc('Show current release.');

// define task to list releases
task('release:list', getDeployTask('releaseListing'))
    ->desc('Show release listing.');

// rollback to previous release
task('release:rollback', getDeployTask('releaseRollback'))
    ->desc('Back to previous release.');

// alias normal deploy task
task('release:deploy', function() {})
    ->desc('Push new release.');

/**
 * Main task
 */
task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:assets',
    'deploy:vendors',
    'deploy:writable',
    'deploy:symlink',
    'cleanup',
])->desc('Deploy your project');


// assign when new tasks are called in pre-existing chain
after('release:deploy',     'deploy');
after('deploy',             'service:php-fpm:reload');
after('deploy',             'service:memcached:restart');
after('rollback',           'service:php-fpm:reload');
after('rollback',           'service:memcached:restart');
after('deploy:shared',      'deploy:shared:fixtures');
after('deploy',             'success');

/* EOF */
