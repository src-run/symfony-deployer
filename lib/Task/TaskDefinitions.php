<?php

/*
 * This file is part of the `src-run/vermicious-deploy-library` project.
 *
 * (c) Rob Frawley 2nd <rmf@src.run>
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace SR\Deployer\Task;

use SR\Console\Std\StdErr;
use SR\Console\Std\StdOut;
use SR\Console\Std\StdOutInterface;

/**
 * Class TaskDefinitions.
 */
class TaskDefinitions
{
    /**
     * @var StdErr
     */
    protected $stdErr;

    /**
     * @var StdOut
     */
    protected $stdOut;

    /**
     * @param StdOutInterface|null $stdOut
     * @param StdOutInterface|null $stdErr
     */
    public function __construct(StdOutInterface $stdOut = null, StdOutInterface $stdErr = null)
    {
        $this->stdOut = $stdOut ?: new StdOut();
        $this->stdErr = $stdErr ?: new StdErr();
    }

    /**
     * Reload FPM configuration task
     */
    public function servicePhpFpmReload()
    {
        try {
            run('sudo /usr/sbin/service php{{php_fpm_ver}}-fpm reload');
        }
        catch (\Exception $e) {
            $this->writeErrorLine('Could not reload php-fpm!');
        }
    }

    /**
     * Restart memcached task
     */
    public function serviceMemcachedRestart()
    {
        try {
            run('sudo /usr/sbin/service memcached restart');
        }
        catch (\Exception $e) {
            $this->writeErrorLine('Could not restart memcached!');
        }
    }

    /**
     * Install/update vendor dependencies
     */
    public function deployVendors()
    {
        $envVars = env('env_vars') ? 'export ' . env('env_vars') . ' &&' : '';
        $options = env('env') === 'dev' ? env('composer_options_dev') : env('composer_options_prod');
        $action = env('env') === 'dev' ? 'update' : 'install';
        $tryRun = sprintf('cd {{release_path}} && %s {{bin/composer}} %s {{composer_options}} %s {{console_more}}', $envVars, $action, $options);
        $caughtRun = sprintf('cd {{release_path}} && %s {{bin/composer}} %s {{composer_options}} %s {{console_more}}', $envVars, 'update', $options);

        try {
            $this->writeLine('Running composer action: <info>%s</info>', $action);
            run($tryRun);
        } catch (\Exception $e) {
            $this->writeLine('Running composer action: <info>update</info> <comment>(fallback attempt)</comment>');
            run($caughtRun);
        }
    }

    /**
     * Dump (compile) bundle assets
     */
    public function assetDump()
    {
        if (!get('dump_assets')) {
            return;
        }

        run('{{bin/php}} {{release_path}}/' . trim(get('bin_dir'), '/') . '/console assetic:dump --env={{env}} {{console_more}}');
    }

    /**
     * Warm-up symfony cache
     */
    public function cacheWarmup()
    {
        run('{{bin/php}} {{release_path}}/' . trim(get('bin_dir'), '/') . '/console cache:warmup  --env={{env}} {{console_more}}');
    }

    /**
     * Migrate database (if available)
     */
    public function databaseMigrate()
    {
        if (!get('migrate_database')) {
            return;
        }

        run('{{bin/php}} {{release_path}}/' . trim(get('bin_dir'), '/') . '/console doctrine:migrations:migrate --env={{env}} {{console_more}}');
    }

    /**
     * Deploy fixtures to remote
     */
    public function deployFixtures()
    {
        if (count($fixtures = get('shared_file_fixtures')) === 0) {
            return;
        }

        $this->writeLine('Uploading <info>%d</info> fixtures:', count($fixtures));

        $serverName = @env('server')['name'] ?: '';

        $replaceAnchors = function ($string, array $replacements = []) {
            foreach ($replacements as $search => $replace) {
                $string = str_replace($search, $replace, $string);
            }

            return $string;
        };

        $replaceCollection = [
            '%server_name' => $serverName
        ];

        foreach ($fixtures as $from => $goto) {
            $fromFile = $replaceAnchors($from, $replaceCollection);
            $gotoFile = $replaceAnchors($goto, $replaceCollection);

            if (false === $fromFile = realpath($fromFile)) {
                $this->writeErrorLine('Fixture not found: <info>%s</info>.', $from);
                continue;
            }

            upload($fromFile, $gotoFile);
        }
    }

    /**
     * Clean (remove) extra front-controllers for production deployments
     */
    public function cleanFrontControllers()
    {
        if (env('env') !== 'prod') {
            return;
        }

        $this->writeLine('Cleaning files from <info>{{deploy_path}}/release/web/</info> for <info>prod</info> deployment:');

        $this->writeLine('Removing: <info>app_.+\.php</info>');
        run("rm -f {{release_path}}/web/app_*.php");

        $this->writeLine('Removing: <info>config.php</info>');
        run("rm -f {{release_path}}/web/config.php");
    }

    /**
     * Deployment ensure-writable paths for web-server writable directories
     */
    public function deployWritable()
    {
        $preOpts = get('writable_use_sudo') ? 'sudo' : '';
        $webUser = $this->getWebUser();

        if (empty($directories = join(' ', get('writable_dirs')))) {
            return;
        }

        try {
            cd('{{release_path}}');

            // osx access rights
            if (null !== $webUser && strpos(run('chmod 2>&1; true'), '+a') !== false) {
                run(sprintf('%s chmod +a "%s allow delete,write,append,file_inherit,directory_inherit" %s', $preOpts, $webUser, $directories));
                run(sprintf('%s chmod +a "`whoami` allow delete,write,append,file_inherit,directory_inherit" %s', $preOpts, $directories));

                return;
            }

            // use posix if no web user is set or no linux acl is available
            if (null === $webUser || !commandExist('setfacl')) {
                run(sprintf('%s chmod 777 -R %s', $preOpts, $directories));

                return;
            }

            // linux acl (using sudo)
            if (!empty($preOpts)) {
                foreach (['u', 'g'] as $type) {
                    run(sprintf('%s setfacl -R -m "%s:%s:rwX" -m "%s:`whoami`:rwX" %s', $preOpts, $type, $webUser, $type, $directories));
                    run(sprintf('%s setfacl -dR -m "%s:%s:rwX" -m "%s:`whoami`:rwX" %s', $preOpts, $type, $webUser, $type, $directories));
                }

                return;
            }

            // linux acl (without sudo, skip any directories that already have acl applies)
            foreach (get('writable_dirs') as $d) {
                // Check if ACL has been set or not
                if (run(sprintf('getfacl -p %s | grep "^user:%s:.*w" | wc -l', $d, $webUser))->toString()) {
                    continue;
                }

                // Set ACL for directory if it has not been set before
                foreach (['u', 'g'] as $type) {
                    run(sprintf('setfacl -R -m "%s:%s:rwX" -m "%s:`whoami`:rwX" %s', $type, $webUser, $type, $d));
                    run(sprintf('setfacl -dR -m "%s:%s:rwX" -m "%s:`whoami`:rwX" %s', $type, $webUser, $type, $d));
                }
            }
        }
        catch (\RuntimeException $e) {
            $this->writeErrorLine('Unable to setup correct permissions for writable dirs. Setup permissions manually or setup sudoers file to not prompt for password');
            throw $e;
        }
    }

    /**
     * Output current release
     */
    public function releaseCurrent()
    {
        writeln('Current release: ' . basename(env('current')));
    }

    /**
     * Display listing of releases
     */
    public function releaseListing()
    {
        $this->writeLine('Release listing:');

        foreach (env('releases_list') as $i => $r) {
            $this->writeLine(' [<comment>%d</comment>] <info>%s</info> (%s) %s', $i, $r, realpath(sprintf('%s/releases/%s', env('deploy_path'), $r)), $i === 0 ? '*active' : '');
        }
    }

    /**
     * Perform rollback previous release
     */
    public function releaseRollback()
    {
        $releases = env('releases_list');

        if (!isset($releases[1])) {
            $this->writeLine('<comment>No release to revert to!</comment>');

            return;
        }

        $releaseDir = "{{deploy_path}}/releases/{$releases[1]}";
        run("cd {{deploy_path}} && ln -nfs $releaseDir current");

        $this->writeLine('Removing release <info>%s</info>.', $releases[0]);
        run("rm -rf {{deploy_path}}/releases/{$releases[0]}");

        $this->writeLine('Now on release <info>%s</info>.', $releases[1]);
    }

    /**
     * @param string  $message
     * @param mixed[] ...$replacements
     */
    protected function writeLine($message, ...$replacements)
    {
        if (function_exists('writeln')) {
            writeln(sprintf($message, ...$replacements));
        } else {
            $this->stdOut->writeLine($message, ...$replacements);
        }
    }

    /**
     * @param string  $message
     * @param mixed[] ...$replacements
     */
    protected function writeErrorLine($message, ...$replacements)
    {
        $this->stdErr->writeLine(sprintf('[ERROR] %s', $message), ...$replacements);
    }

    /**
     * @return null|string
     */
    protected function getWebUser()
    {
        if (null !== ($webUser = get('http_user'))) {
            return $webUser;
        }

        if (null !== ($webUser = env('http_user'))) {
            return $webUser;
        }

        $webUser = run('ps axo user,comm | grep -E \'[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx\' | grep -v root | head -1 | cut -d\  -f1')->toString();

        return empty($webUser) ? null : $webUser;
    }
}

/* EOF */
