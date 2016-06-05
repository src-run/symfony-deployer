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

use Deployer\Task\Context;
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
            $this->writeTaskLine('Running action: <info>%s</info>', $action);
            run($tryRun);
        } catch (\Exception $e) {
            $this->writeTaskLine('Running action: <info>update</info> <comment>(fallback attempt)</comment>');
            run($caughtRun);
        }
    }

    /**
     * Deploy assets
     */
    public function deployAssets()
    {
        $assets = implode(' ', array_map(function ($asset) {
            return "{{release_path}}/$asset";
        }, get('assets')));

        $time = date('Ymdhi.s');

        run("find $assets -exec touch -t $time {} ';' &> /dev/null || true");
    }

    /**
     * Create cache directory
     */
    public function deployCreateCacheDirectory()
    {
        env('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');
        run('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');
        run('mkdir -p {{cache_dir}}');
        run("chmod -R g+w {{cache_dir}}");
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

        $i = 1;
        foreach ($fixtures as $from => $goto) {
            $fromFile = env()->parse(sprintf('%s/%s', getcwd(), $replaceAnchors($from, $replaceCollection)));
            $gotoFile = env()->parse(sprintf('{{deploy_path}}/shared/%s', $replaceAnchors($goto, $replaceCollection)));
            $gotoPath = dirname($gotoFile);

            if (false === $fromFile = realpath($fromFile)) {
                $this->writeErrorLine('Fixture not found: <info>%s</info>.', sprintf('%s/%s', getcwd(), $from));
                continue;
            }

            $this->writeTaskLine('Uploading file: <info>%s</info> to <info>%s</info>', $fromFile, $gotoFile);

            run(sprintf('if [ -f $(echo %s) ]; then rm -rf %s; fi', $gotoFile, $gotoFile));
            run(sprintf('if [ ! -d $(echo %s) ]; then mkdir -p %s; fi', $gotoPath, $gotoPath));
            Context::get()
                ->getServer()
                ->upload($fromFile, $gotoFile);

            $i++;
        }
    }

    /**
     * Clean (remove) extra front-controllers for production deployments
     */
    public function cleanSymfonyFrontControllers()
    {
        if (env('env') !== 'prod') {
            return;
        }

        run('rm -f {{release_path}}/web/app_*.php');
        run('rm -f {{release_path}}/web/config.php');
    }

    /**
     * Clean (remove) extra front-controllers for production deployments
     */
    public function cleanSilexFrontControllers()
    {
        if (env('env') !== 'prod') {
            return;
        }

        run('rm -f {{release_path}}/web/app_dev.php');
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
        $this->writeTaskLine('Current: <info>%s</info> (<comment>%s</comment>)', basename(env('current')), env('current'));
    }

    /**
     * Display listing of releases
     */
    public function releaseListing()
    {
        foreach (env('releases_list') as $i => $r) {
            $this->writeTaskLine('[%s] <info>%s</info> (<comment>%s</comment>) <fg=red>%s</>',
                str_pad(((string)++$i), 2, '0', STR_PAD_LEFT), $r, run(sprintf('realpath %s/releases/%s', env('deploy_path'), $r)), $i === 1 ? '<- current' : '');
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

        $this->writeTaskLine('Removing: <info>%s</info> (<comment>%s</comment>)', basename(env('current')), env('current'));
        $this->writeTaskLine('Rolling back...');

        $releaseDir = "{{deploy_path}}/releases/{$releases[1]}";
        run("cd {{deploy_path}} && ln -nfs $releaseDir current");
        run("rm -rf {{deploy_path}}/releases/{$releases[0]}");

        $this->writeTaskLine(env()->parse('Current: <info>%s</info> (<comment>{{deploy_path}}/releases/%s</comment>)'), $releases[1], $releases[1]);
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
    protected function writeTaskLine($message, ...$replacements)
    {
        $this->writeLine(sprintf('  - %s', $message), ...$replacements);
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
