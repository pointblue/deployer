<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;


/**
 *
 *
 * Configuration
 *
 *
 * These are the default configuration setting for all deployed projects.
 * Any variable can be overridden by setting the same variable in your project's deploy.php file
 *
 *
 *
 */

//default to test server preventing accidental changes to production if the user doesn't supply a stage/host argument
set('default_stage', 'nonprod-aws');

//not sure if these settings are needed, but leaving them since everything is working
set('ssh_type', 'native');
set('ssh_multiplexing', true);

//the filename of the metadata output for this build. path defaults to project root or `public` folder if available
set('build_meta_output', 'deployer_php_build.json');

//using relative links will turn /myapp/current into /myapp/releases/1, which is saved in some cases causing stale
// references when the release of a dependency changes
set('use_relative_symlink', false);

//log all actions to a local file
set('log_file', 'deployer_php_build.log');


/**
 * Options that can be used in the command line
 */
option('pb-test', null, InputOption::VALUE_NONE, 'Have a Point Blue task print to console instead of executing');


/**
 *
 *
 * Common binaries used for deployment.
 *
 *
 * I'm not sure what the reason is for doing this, but it's a pattern used in the
 * author's source code so I decided to continue that pattern.
 *
 *
 */

//yarn
set('bin/yarn', function () {
    return (string)run('which yarn');
});

//bower
set('bin/bower', function () {
    return (string)run('which bower');
});

//grunt
set('bin/grunt', function () {
    return (string)run('which grunt');
});

//gulp
set('bin/gulp', function () {
    return (string)run('which gulp');
});

/**
 *
 *
 * git variables
 *
 *
 * These variable show information about the currently clone git repo on the deployment server
 * deploy:update_code task must be run first before these variables will work
 *
 *
 */

//the revision id of the currently deployed repo
set('git_rev', function(){
    return (string)run('cd {{release_path}} && git rev-parse HEAD');
});

//the tag, if any, associate with the current revision
set('git_tag', function (){
    return (string)run('cd {{release_path}} && git tag --points-at HEAD');
});

//the branch associate with the current revision
set('git_branch', function (){
    return (string)run('cd {{release_path}} && git rev-parse --abbrev-ref HEAD');
});

//the github url for the current repo
set('git_repo_url', function(){
    $hostUrl = 'https://github.com';
    $repo = get('repository');
    $splitByColon = explode(':', $repo);
    $repoName = substr($splitByColon[1], 0, strlen($splitByColon[1]) - 4 );
    return "{$hostUrl}/{$repoName}";
});

//the github url for the current revision
set('git_rev_url', function (){
    $repoUrl = get('git_repo_url');
    $rev = get('git_rev');
    return "{$repoUrl}/tree/{$rev}";
});

//Install node_modules defined in yarn.lock
desc('Install node modules (npm/yarn)');
task('deploy:node_modules', function () {
    run("cd {{release_path}} && {{bin/yarn}} install --frozen-lockfile");
});

//use yarn to run the 'prod' script with should be define in the package.json of the repo in the "scripts" property
// this command should run any tasks that compile assets such as js, cs, sass, and so on.
desc("build runtime assets (css, js, etc)");
task('deploy:build_assets', function(){
    run("cd {{release_path}} && yarn run prod");
});





/**
 *
 *
 * TASKS
 *
 * Below are defined tasks that can be used in your project's deploy.php script
 *
 *
 */





/**
 *
 * # deploy:build_metadata
 *
 * Creates the file deployer_php_build.json which contains details about the build that is publicly accessible.
 * The files will be written to the `public` folder of the project if available, otherwise the project's root path is
 * used.
 *
 * It also logs the revision id, revision url, and launch_url if available.
 *
 * If you include a `launch_url` property in your server.yml file, it will be used to produce a link in the output file.
 */
desc('Write information about the build to the deployment server');
task('deploy:build_metadata', function (){

    //TODO: Turn these into functions instead of using config settings to pass the value to other tasks
    //get the revision for the exact commit that's been built
    $rev = get('git_rev');
    $tag = get('git_tag');
    $branch = get('git_branch');
    $repoUrl = get('git_repo_url');

    //decide the path for our output file
    if (test("[ -d {{release_path}}/public ]")) {
        $path = "{{release_path}}/public";
    }
    else
    {
        $path = "{{release_path}}";
    }

    $filename = get('build_meta_output');

    //decide the final output name for our build document
    $outputFile = "{$path}/{$filename}";

    //timestamp for the build
    $timestamp = date(DATE_ATOM);

    $gitRevUrl = get('git_rev_url');

    $buildDetails = [
        "git_rev" => $rev,
        "git_rev_url" => escapeshellarg($gitRevUrl),
        "git_tag" => $tag,
        "git_branch" => $branch,
        "timestamp" => $timestamp,
        "deployer_php_release" => get('release_name')
    ];

    //add pull request urls if this is the dev branch, for convenience
    if( $branch === 'dev' )
    {
        $buildDetails['git_rev_pr_url'] = escapeshellarg("{$repoUrl}/compare/master...{$rev}");
        $buildDetails['git_branch_pr_url'] = escapeshellarg("{$repoUrl}/compare/master...dev");
    }

    $launchUrl = has('launch_url') ? get('launch_url'):'';
    if(! empty($launchUrl) )
    {
        $buildDetails['launch_url'] = escapeshellarg($launchUrl);
    }

    //content to add to the build document
    $content = json_encode($buildDetails);


    //write the json contents to the output file
    run("echo '" . $content . "' > {$outputFile}");


    //log the reference
    logger("git revision built: {$rev}");
    logger("See revision in github: {$gitRevUrl}");
    logger("Launch url: {$launchUrl}");

});

/**
 *
 *
 * # announce:rev_url
 *
 *
 * Produces a link in the command line to the deployed revision in github if running in verbose or very verbose mode.
 *
 *
 */
desc('announce the revision url for this release');
task('announce:rev_url', function(){
    $gitRevUrl = get('git_rev_url');
    if( ! empty($gitRevUrl) && (isVerbose() || isVeryVerbose()) )
    {
        $message = "See revision in github: $gitRevUrl";
        writeln($message);
    }
});
//announcements come after the success task
after('success', 'announce:rev_url');

/**
 *
 *
 * # announce:launch_url
 *
 *
 *
 * Produces a link to the launch_url if running in verbose or very verbose mode.
 *
 * The launch_url property can be defined in the servers.yml
 *
 *
 */
desc('announce the launch url for this deployment');
task('announce:launch_url', function(){
    $launchUrl = has('launch_url') ? get('launch_url'):'';
    if( ! empty($launchUrl) && (isVerbose() || isVeryVerbose()) )
    {
        $message = "Launch url: {$launchUrl}";
        writeln($message);
    }
});
//announcements come after the success task
after('success', 'announce:launch_url');


/**
 *
 *
 * # slack:notify
 *
 *
 *
 * Sends a notification to Slack via a webhook announcing that the build has completed.
 * Also has additional details about the build and include links to the deployed version for convenience.
 *
 *
 */
desc('Notify slack of build status');
task('slack:notify', function (){
    $webhookEndpoint = get('slack_webhook_url');

    //cannot complete function without webhook endpoint
    if(empty($webhookEndpoint))
    {
        $message = 'Slack webhook not found (slack_webhook_url). Notification not sent.';
        writeln($message);
        logger($message);
        return;
    }

    $repo = get('repository');
    $rev = get('git_rev');
    //TODO: For some reason when I use get('host') or get('stage') here, it doesn't work
    // [RuntimeException] Configuration parameter `stage` does not exists.
    $buildFilename = get('build_meta_output');
    if(has('launch_url'))
    {
        $launchUrl = get('launch_url');
        $toUrl = "\nLaunch URL: {$launchUrl}";
        $buildDetails = "\nBuild details: {$launchUrl}/{$buildFilename}";
    }
    else
    {
        $toUrl = '';
        $buildDetails = '';
    }

    $message = "{$repo} has been successfully deployed" .
        "\nGit revision: {$rev}" .
        "{$toUrl}" .
        "{$buildDetails}"
    ;

    $payload = [
        'text' => $message
    ];

    $curlCommand = "curl -s -X POST -H 'Content-type: application/json' --data '" . json_encode($payload) . "' {$webhookEndpoint}";
    runLocally($curlCommand);

});
//announcements come after the success task
after('success', 'slack:notify');


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
        $classmap_file = get("release_path") . "/$appPath/vendor/composer/autoload_classmap.php";

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
 * Run after deploy:clear_paths task
 *
 */
desc('Common Point Blue deployer tasks to run just before releasing the app');
task('deploy:pb_deployer_post_hook', [
    'deploy:symlink_envs',
    'deploy:update_autoload_classmap',
    'deploy:build_metadata'
]);
