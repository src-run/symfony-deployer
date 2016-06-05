<?php

/*
 * This file is part of the `src-run/vermicious-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

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
set('shared_file_fixtures',  []);
set('shared_files', [
    'app/config/parameters.yml'
]);
set('assets', [
    'web/css',
    'web/images',
    'web/js'
]);

/* EOF */
