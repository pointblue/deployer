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

//default to empty string
set('slack_webhook_url', '');

//read special configuration files to load secrets
//if .slack_webhook_url file exists in the path that the `include` function can use
if (file_exists(stream_resolve_include_path('.slack_webhook_url')))
{
    //output the contents of the file to the buffer
    ob_start();
    include('.slack_webhook_url');
    //then put the buffer contents in a variable
    $slack_webhook_url = ob_get_clean();
    //set the variable as a configuration in deployer
    set('slack_webhook_url', $slack_webhook_url);
}


//default to test server preventing accidental changes to production if the user doesn't supply a stage/host argument
set('default_stage', 'nonprod-aws');

//not sure if these settings are needed, but leaving them since everything is working
set('ssh_type', 'native');
set('ssh_multiplexing', true);

//using relative links will turn /myapp/current into /myapp/releases/1, which is saved in some cases causing stale
// references when the release of a dependency changes
set('use_relative_symlink', false);

//log all actions to a local file
set('log_file', 'deployer_php_build.log');

/**
 *
 *
 * Configuration: Point Blue specific
 *
 *
 * These variables are not part of deployer, but are required by the additional tasks created in this library
 *
 *
 */

//the filename of the metadata output for this build. path defaults to project root or `public` folder if available
set('build_meta_output', 'deployer_php_build.json');

//create this with an empty array to be sure that our function which expecting `common_symlinks` to be set
set('common_symlinks', []);


/**
 *
 *
 * Options that can be used in the command line
 *
 *
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


/**
 *
 *
 * TASKS
 *
 * Below are defined tasks that can be used in your project's deploy.php script
 *
 *
 */


//Install node_modules defined in yarn.lock
desc('Install node modules (npm/yarn)');
task('deploy:node_modules', function () {
    run("cd {{release_path}} && {{bin/yarn}} install --frozen-lockfile");
});

//use yarn to run the 'prod' script with should be define in the package.json of the repo in the "scripts" property
// this command should run any tasks that compile assets such as js, cs, sass, and so on.
desc("Install bower components");
task('deploy:bower_components', function(){
    run("cd {{release_path}} && {{bin/bower}} install");
});

//use yarn to run the 'prod' script with should be define in the package.json of the repo in the "scripts" property
// this command should run any tasks that compile assets such as js, cs, sass, and so on.
desc("build runtime assets (css, js, etc)");
task('deploy:build_assets', function(){
    run("cd {{release_path}} && yarn run prod");
});



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

    //the default build details
    $buildDetails = [
        //time the build finishes (if this task is run last)
        "timestamp" => $timestamp,
        //current branch of the deployed repo
        "git_branch" => $branch,
        //current tag of the deployed repo
        "git_tag" => $tag,
        //current revision of the deployed repo
        "git_rev" => $rev,
        //github link to the deployed revision
        // useful to see the exact code that is currently deployed
        "git_rev_url" => escapeshellarg($gitRevUrl)
    ];

    //add compare urls if this is not the master branch for convenience
    //we assume that this is useful only for branches other than master
    if( $branch !== 'master' )
    {
        //the dev branch compares to the master branch and everything else compares to the dev branch
        $compareBase = $branch === 'dev' ? 'master' : 'dev';

        //compare the current revision to the base branch
        // useful if the dev has changed since deployment
        $buildDetails['git_rev_compare_url'] = escapeshellarg("{$repoUrl}/compare/{$compareBase}...{$rev}");
        //compare the current branch to the base branch
        // useful for creating the most common pull requests
        $buildDetails['git_branch_pr_url'] = escapeshellarg("{$repoUrl}/compare/{$compareBase}...{$branch}");
        //compare the current tag against the base branch
        // useful to create a pr for a specific version you've tested
        // useful to create a pr then continue developing on the same branch without changing the diff of the pr
        // (which would happen if you did a branch pr and then later pushed more commits to the compared branch before
        // its merged)
        $buildDetails['git_tag_pr_url'] = ! empty($tag) ? escapeshellarg("{$repoUrl}/compare/{$compareBase}...{$tag}") : '';
    }

    $launchUrl = has('launch_url') ? get('launch_url'):'';
    if(! empty($launchUrl) )
    {
        $buildDetails['launch_url'] = escapeshellarg($launchUrl);
    }

    //always show the release name from deployer php, but add it last
    $buildDetails['deployer_php_release'] = get('release_name');

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
 *
 * #deploy:common_symlinks
 *
 *
 * This creates symlinks for common libraries that are necessary for composer to resolve all of it's dependencies. This
 * is for libraries that are shared between apps and are deployed in a single location. The main libraries this is meant
 * to support are:
 *
 *   - deju2
 *   - deju2-renew
 *   - deju3
 *
 * To do this, you must add a configuration to your deploy.php as:
 *
 * set('common_symlinks', [
 *    "base_path" => "{{deploy_path}}/apps/common",
 *    "libs" => [
 *     "deju2",
 *     "deju2-renew",
 *     "deju3"
 *   ]
 * ]);
 *
 * Where the `base_path` property is the path where your symlinks will go and the `libs` property is an array of library
 * names where each library is a deployed repo that will be symlinked. The library must be defined in the $libNames
 * array of this task.
 *
 * Example:
 *
 * You have a composer.json file with deju autoload dependencies:
 *
 *     "autoload-dev": {
 *       "classmap": [
 *         "../../apps/common/deju2-renew/lib/db/nodedb/build/classes/nodedb/",
 *         "../../apps/common/deju2-renew/lib/deju/",
 *         "../../apps/common/deju2-renew/Deju2.php",
 *         "../../apps/common/deju3/vendor/propel/propel1/runtime/lib/Propel.php"
 *        ],
 *        ...
 *
 * This means that from the app's release path, it's going to try and resolve those dependencies.
 * If your app is deployed at `/path/to/deploy/my-app/releases/22` then composer will try to find the files at:
 * `/path/to/deploy/my-app/apps/common/deju2-renew/Deju2.php` for example. This doesn't work because our dependencies
 * aren't at that location. To fix it, we add a symlink at that location which **will** resolve to the correct
 * location, which is what the deploy:common_symlinks task does.
 *
 *
 *
 */
desc('Create symlinks to point blue dependencies in composer.json');
task('deploy:common_symlinks', function(){

    //Note: I use the term 'library' here because this was originally intended for the deju libraries. Library can also
    // be thought of as any git repo that is a dependency and needs to be symlinked for this deployment to resolve a
    // path to it

    //This maps a library name to the name of a symlink that will be create for it
    // each key is a library name
    // each value is an associate array with two properties: link_name and current_release_path
    //   link_name is the name of the symbolic link that will be created
    //   current_release_path is the path to the current release of the library
    $libNames = [
        "deju2" => [
            "link_name" => 'deju2',
            "current_release_path" => "{{deploy_path}}/../deju2/current"
        ],
        "deju2-renew" => [
            "link_name" => 'deju2-renew',
            "current_release_path" => "{{deploy_path}}/../deju2-renew/current"
        ],
        "deju3" => [
            "link_name" => 'deju3',
            "current_release_path" => "{{deploy_path}}/../deju3/current"
        ],
        "apps-authentication-common" => [
            "link_name" => 'authentication',
            "current_release_path" => "{{deploy_path}}/../apps-authentication-common/current"
        ]
    ];

    $commonSymlinks = get('common_symlinks');

    //common_symlinks is an empty array by default. check if the necessary properties are available to run the function.
    if(!array_key_exists('base_path', $commonSymlinks) || !array_key_exists('libs', $commonSymlinks))
    {
        return;
    }

    $basePath = $commonSymlinks['base_path'];
    $libs = $commonSymlinks['libs'];

    //create the path where the symlinks will go
    run("mkdir -p {$basePath}");

    //create a symlink for each lib in the libs array
    for($i=0;$i<count($libs);$i++)
    {
        $lib = $libs[$i];
        if(!array_key_exists($lib, $libNames))
        {
            throw new \Exception('The lib defined in common_symlinks->libs is not known to deploy:common_symlinks. ' .
                'Search $libNames in the pointblue/deployer repo for more information.');
        }

        //first argument is where the link will point to
        $currentReleasePath = $libNames[$lib]['current_release_path'];

        //second argument is the name of the link that will be created
        $linkName = $libNames[$lib]['link_name'];

        run("cd {$basePath} && {{bin/symlink}} {$currentReleasePath} {$linkName}");
    }

});


/**
 *
 *
 *
 *
 * TASK ORDER
 *
 *
 * Configure the order that our custom tasks will run in
 *
 *
 *
 */






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

//before running the composer install command, create the symlinks that will be needed, if any
before('deploy:vendors', 'deploy:common_symlinks');

/**
 *
 * deploy:pb_deployer_post_hook task order
 *
 * the hook is added after two tasks- artisan:optimize and deploy:clear_paths
 * this works because we assume that a deployment will only include one or the other, not both.
 *
 *
 */

//the artisan:optimize task is only executed from the laravel recipe.
after('artisan:optimize', 'deploy:pb_deployer_post_hook');

//the deploy:clear_paths task is only in the default deploy.php file which uses the common.php recipe
after('deploy:clear_paths', 'deploy:pb_deployer_post_hook');

// by default, deployments are unlocked if they fail
after('deploy:failed', 'deploy:unlock');
