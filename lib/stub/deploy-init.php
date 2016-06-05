<?php

/*
 * This file is part of the `src-run/vermicious-symfony-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

if (file_exists($includeFile = __DIR__ . '/../../vendor/autoload.php') ||
    file_exists($includeFile = __DIR__ . '/../../../../autoload.php')) {
    require_once($includeFile);
} else {
    $stdErr = new \SR\Console\Std\StdErr();
    $stdErr->writeLine('Could not locate autoload file "%s/../../(vendor|../..)/autoload.php".');
    exit(255);
}

// include the base recipe
requireDeployVendorInclude('deployer/deployer/recipe/symfony3.php');

// import server list
serverList(__DIR__ . '/../../.deploy-servers.yml');

// default build stage, git remote, releases to keep, composer path, shared files, and env vars
set('default_stage',         'dev-local');
set('keep_releases',         12);
set('composer_command',      '/usr/local/bin/composer');
set('ssh_type',              'ext-ssh2');
set('dump_assets',           false);
set('migrate_database',      false);
env('env_vars',              'SYMFONY_ENV={{env}}');
env('console_more',          '--no-interaction');
env('composer_options',      '--verbose --prefer-dist --no-progress');
env('composer_options_prod', '--no-dev --optimize-autoloader');
env('composer_options_dev',  '--dev');
set('shared_files', [
    'app/config/parameters.yml'
]);
set('assets', [
    'web/css',
    'web/images',
    'web/js'
]);
set('shared_file_fixtures', [
    __DIR__.'/../app/config/parameters.%server_name.yml' => '{{deploy_path}}/shared/app/config/parameters.yml'
]);

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

// assign when new tasks are called in pre-existing chain
after('release:deploy', 'deploy');
after('deploy',         'service:php-fpm:reload');
after('deploy',         'service:memcached:restart');
after('rollback',       'service:php-fpm:reload');
after('deploy:vendors', 'database:migrate');
after('deploy:shared',  'deploy:shared:fixtures');

/* EOF */
