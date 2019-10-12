<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

option('pb-test', null, InputOption::VALUE_NONE, 'Have a Point Blue task print to console instead of executing');

/**
 *
 *
 * # deploy:update_autoload_classmap
 *
 *
 * Search for the deju release path and replace with `current` so it's always pointing to the correct release
 *
 * This task will only run if the setting `update_autoload_classmap` is set. This should be an array, where each item
 * is the path of the folder that contains a composer `vendor` folder, relative to the release path
 *
 * *Example*
 *
 * This is your repo's file structure:
 *
 * myrepo
 *   |
 *   |------/app1
 *   |------/app2
 *   |------/app3
 *   |------/deploy.php
 *
 * Each app has a file at `vendor/composer/autoload_classmap.php` with references to at least one deju library that
 * needs to be updated to refer to the current release.
 *
 * In this case, your `servers.yml` file would have:
 *
 * update_autoload_classmap:
 *   - "app1"
 *   - "app2"
 *   - "app3"
 *
 * The results would be that the following files would replace references to `deju2/release/x`, `deju2/releases/x`, and
 * `deju3/releases/x` with `deju2/current`, `deju2-renew/current`, and `deju3/current`:
 *
 * - {{release_path}}/app1/vendor/composer/autoload_classmap.php
 * - {{release_path}}/app2/vendor/composer/autoload_classmap.php
 * - {{release_path}}/app3/vendor/composer/autoload_classmap.php
 *
 */
desc('Set deju autoload paths to current release');
task('deploy:update_autoload_classmap', function(){

    //require a setting to run this function
    if( ! has('update_autoload_classmap') ) {
        return;
    }
    $appPaths = get('update_autoload_classmap');

    foreach ($appPaths as $appPath) {
        ;
        // the path's we are looking for
        // reference: https://www.tutorialspoint.com/unix/unix-regular-expressions.htm | http://www.grymoire.com/Unix/sed.html
        $find = "(deju(2|3|2-renew))\/releases\/[0-9]+";

        // the new replacement path
        $replace = "\\1\/current";

        //composer classmap file for this release
        $classmap_file = get("deploy_path") . "/current/$appPath/vendor/composer/autoload_classmap.php";

        $replaceCommand = "sed -i -r 's/$find/$replace/g' $classmap_file";

        if (input()->getOption('pb-test')) {
            writeln($replaceCommand);
        } else {
            run($replaceCommand);
        }

    }
});

/**
 *
 *
 * # deploy:symlink_envs
 *
 *
 * If the env_symlinks env variable is set (in deployer), a symlink will be created for each target/destination pair
 * in the array. This allows deployments to define where their own environment configs are per server.
 *
 * Be sure to use `set('use_relative_symlink', false);` in your deployer script! If not, the symlink will point to the
 * release version of the destination file, not the current version.
 *
 * ```
 * Example in servers.yml:
 *
 *   myhost:
 *     user: ubuntu
 *     pem_file: ~/.ssh/pem.pem
 *     host: example.org
 *     stage: prod
 *     deploy_path: /my/deploy/path
 *     branch: master
 *     env_symlinks: #link configs in shared_files to actual configs
 *       - target: "{{deploy_path}}/../environment_configs/current/aws/deju2/apps_all/build/conf/apps_all-conf-prod.php"
 *         destination: "{{deploy_path}}/shared/lib/db/apps_all/build/conf/apps_all-conf-prod.php"
 *       - target: "{{deploy_path}}/../environment_configs/current/aws/deju2/auth/build/conf/auth-conf-prod.php"
 *         destination: "{{deploy_path}}/shared/lib/db/auth/build/conf/auth-conf-prod.php"
 * ```
 *
 */
desc('Symlink target/destination pairs in env_symlinks array');
task('deploy:symlink_envs', function(){

    $envSymlinks = has('env_symlinks') ?  get('env_symlinks') : [];

    //create a symlink for each target / destination pair
    foreach($envSymlinks as $envSymlink){

        $command = "{{bin/symlink}} {$envSymlink['target']} {$envSymlink['destination']}";

        if( input()->getOption('pb-test') ){
            writeln($command);
        } else {
            run($command);
        }

    }

});

desc('Log pull request information for deployment if available');
task('deploy:log_pull_request', function(){
        $prId = input()->getOption('pr-id');
        writeln($prId);
        if( $prId ){
        if(file_exists("/tmp/pr_{$prId}.json")){
                writeln('found file');
                $json = file_get_contents("/tmp/pr_{$prId}.json");
                $prEvent = json_decode($json);
                $content = "<p>" . date(DATE_W3C) . ' - ' . $prEvent->repository->full_name . ' - ' . '<a href="' . $prEvent->pull_request->html_url . '">' . $prEvent->pull_request->html_url . '</a></p>' . "\n";
                writeln($content);
                //TODO: Make the output file path variable
                file_put_contents("/var/www/html/webhooks/git.html", $content, FILE_APPEND);
                //TODO: Add Slack webhook here
        }

        }

});



/**
 *
 * things we want all laravel apps to do after they're done building, but before release
 *
 * just add this to you project's deploy.php: after('artisan:optimize', 'deploy:pb_deployer_laravel_post_hook');
 *
 * DEPRECATED: Use environment variables to change the behavior of the deployment and only add another hook task if you
 * really need it.
 *
 */
desc('DEPRECATED');
task('deploy:pb_deployer_post_hook_laravel', [
    'deploy:update_autoload_classmap',
]);

/**
 * Tasks we want to do every time for all app deployments
 *
 * Run after last task before deploy:symlink
 *
 */
desc('Common Point Blue deployer tasks to run just before releasing the app');
task('deploy:pb_deployer_post_hook', [
    'deploy:symlink_envs',
    'deploy:update_autoload_classmap'
]);
