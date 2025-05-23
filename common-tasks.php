<?php

namespace Deployer;

require_once 'recipe/common.php';

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
if (file_exists(getenv('HOME') . '/.slack_webhook_url'))
{
    //output the contents of the file to the buffer
    ob_start();
    include(getenv('HOME') . '/.slack_webhook_url');
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

//keep a limited number of release to balance the ability to rollback and the amount of store space used for deployments
set('keep_releases', 5);

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


//by default, use the origin url defined on the machine deploying this repo
set('repository', (string)exec('git remote get-url origin'));


/**
 *
 *
 *
 * Support functions
 *
 *
 *
 */

/**
 *
 * taskExists
 *
 * true if the named task exists
 *
 * @param $name
 * @return string
 */
function taskExists($name)
{
    $deployer = Deployer::get();
    return $deployer->tasks->has($name);
}


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

//the revision short id of the currently deployed repo
set('git_rev_short', function(){
    return (string)run('cd {{release_path}} && git rev-parse --short HEAD');
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
 * other variables
 *
 *
 */

function has_yarn_lock(){
    return file_exists("yarn.lock");
}

function has_package_json(){
    return file_exists("package.json");
}

function has_package_lock_json(){
    return file_exists("package-lock.json");
}

function has_bower_json(){
    return file_exists("bower.json");
}

function has_composer_json(){
    return file_exists("composer.json");
}

//true if there is a package.json file with a `scripts.prod` property
function has_node_build_command(){
    $hasBuildCommand = false;
    $packageFile = "package.json";
    if(file_exists($packageFile))
    {
        $contents = file_get_contents($packageFile);
        $packageJson = json_decode($contents, TRUE);    //turn this into a PHP associative array
        //if the package.json file has a `scripts` property and that property has a `prod` property
        // then this project's assets can but built using `npm run prod` or `yarn run prod`
        $hasBuildCommand = (bool)(array_key_exists('scripts', $packageJson) && array_key_exists('prod', $packageJson['scripts']));
    }
    return $hasBuildCommand;
}

function has_laravel(){
    $isLaravel = false;
    $composerFile = "composer.json";
    if(file_exists($composerFile))
    {
        $contents = file_get_contents($composerFile);
        $composerJson = json_decode($contents, TRUE);    //turn this into a PHP associative array
        //if the composer.json file has a `require` property and that property has a `laravel/framework property
        // then this is a laravel app
        $isLaravel = (bool)(array_key_exists('require', $composerJson) && array_key_exists('laravel/framework', $composerJson['require']));
    }
    return $isLaravel;
}

function has_cmsms(){

    $includeFile = "include.php";
    if(file_exists($includeFile))
    {
        $contents = file_get_contents($includeFile);
        return (bool)preg_match('/dev\.cmsmadesimple\.org\/latest_version\.php/', $contents);

    }
    else
    {
        return false;
    }

}

/**
 * used to detect a project that has cmsms AND deju
 * @return bool
 */
function has_cmsms_deju()
{
    return has_cmsms() && file_exists('deju.php');
}

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
 *
 * deploy:node_modules
 *
 * installs dependencies with npm or yarn depending on the lock files available.
 *
 * if yarn.lock exists, use yarn to install with the frozen lockfile option (no package updates, install only)
 * if not, but package-lock.json exists, use npm to install with the ci command
 * if not, there are no lock file. just use yarn to install
 *
 */
desc('Install node modules (npm/yarn)');
task('deploy:node_modules', function () {

    //if a yarn.lock file is present
    if(has_yarn_lock())
    {
        //use yarn to install node_modules
        run("cd {{release_path}} && {{bin/yarn}} install --frozen-lockfile");
    }
    elseif(has_package_lock_json())    //if not yarn.lock, but has a package-lock.json file
    {
        //use npm with the ci option to install node_modules
        run("cd {{release_path}} && {{bin/npm}} ci");
    }
    else    //no lock files, just use yarn to install
    {
        run("cd {{release_path}} && {{bin/yarn}} install");
    }

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

    //change our working path to the release path just for this task
    // this will ensure our test function can run in a loop correctly
    $lastWorkingPath = workingPath();
    $releasePath = get('release_path');
    set('working_path', $releasePath);

    //make sure it has the latest tags
    run('{{bin/git}} fetch --tags');

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

    //create a new tag to suggest for master and dev branches

    //get year/week of todays date
    $newTag = 'v' . date('Y') . '.' . (integer)date('W');
    $subversion = '';

    //if the tag name we want to use is in conflict with an existing tag
    while( test("git rev-parse \"{$newTag}{$subversion}\" >/dev/null 2>&1") )
    {
        //increment a subversion for the tag

        //if the string contains a single dot, it doesn't have a subversion number yet
        if( preg_match_all('/\./', $newTag) === 1 )
        {
            //add a period to separate it from the main version
            $newTag .= '.';
            //and set a start value for the subversion that can be increment each loop
            $subversion = 1;
        }
        else
        {
            //increment this subversion to be tested
            $subversion++;
        }
    }

    //add the subversion to the tag
    $newTag .= $subversion;

    //add compare urls if this is not the master branch for convenience
    //we assume that this is useful only for branches other than master
    if( $branch !== 'master' )
    {


        if($branch === 'dev')
        {
            $compareBase = 'master';
            //the tag suggestion is the new tag plus the release candidate version suggestion
            $buildDetails['git_tag_suggestion'] = $newTag . '-rcX';
        }
        else
        {
            $compareBase = 'dev';
            $buildDetails['git_tag_suggestion'] = '';
        }

        //compare the current revision to the base branch
        // useful if the dev has changed since deployment
        $buildDetails['git_rev_compare_url'] = escapeshellarg("{$repoUrl}/compare/{$compareBase}...{$rev}");
        //compare the current branch to the base branch
        // useful for creating the most common pull requests
        $buildDetails['git_branch_pr_url'] = escapeshellarg("{$repoUrl}/compare/{$compareBase}...{$branch}");


    }
    else
    {
        //Add a link to the master commits so I can easily calculate the next version number
        $buildDetails['git_master_commits_url'] = escapeshellarg("{$repoUrl}/commits/master");

        //only offer a suggestion if the currently deployed version is not tagged
        // which implies that it is a release candidate
        $buildDetails['git_tag_suggestion'] = $tag ? '' : $newTag;
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
    run("echo '{$content}' > {$outputFile}");

    //write the json contents to the local dir it was deployed from
    runLocally("echo '{$content}' > ./{$filename}");


    //log the reference
    logger("git revision built: {$rev}");
    logger("See revision in github: {$gitRevUrl}");
    logger("Launch url: {$launchUrl}");

    set('working_path', $lastWorkingPath);

});
after('success', 'deploy:build_metadata');

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
 * # announce:git_pr_url
 *
 *
 * Produces a link in the command line to the deployed revision in github if running in verbose or very verbose mode.
 *
 *
 */
desc('announce the revision url for this release');
task('announce:git_pr_url', function(){
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

    $hostAlias = [
        "data.pointblue.org"=>[
            "name"=>"production",
            "emoji"=>":pointblue:"
        ],
        "data3.pointblue.org"=>[
            "name"=>"old aws",
            "emoji"=>":construction:"
        ],
        "data-test.pointblue.org"=>[
            "name"=>"nonprod-aws",
            "emoji"=>":construction:"
        ],
        "nonprod-php56"=>[
            "name"=>"nonprod-php56",
            "emoji"=>":construction:"
        ],
        "nonprod-php52"=>[
            "name"=>"nonprod-php52",
            "emoji"=>":construction:"
        ]
    ];

    $webhookEndpoint = get('slack_webhook_url');
    $branch = get('git_branch');

    //cannot complete function without webhook endpoint
    if(empty($webhookEndpoint))
    {
        $longMessage = 'Slack webhook not found (slack_webhook_url). Notification not sent.';
        writeln($longMessage);
        logger($longMessage);
        return;
    }

    $repoName = preg_replace('/^(git@github.com:)(.+)\.git$/', '$2', get('repository'));
    $revShort = get('git_rev_short');
    $revUrl = get('git_rev_url');
    $repoUrl = get('git_repo_url');
    $buildFilename = get('build_meta_output');
    $server = get('server');
    $configHost = $server['host'];
    //if the host in the configuration has an alias, use that. if not, just use the name set in the config
    $hostName = array_key_exists($configHost, $hostAlias) ? $hostAlias[$configHost]['name'] : $configHost;
    $hostEmoji = array_key_exists($configHost, $hostAlias) ? $hostAlias[$configHost]['emoji'] : '';
    $hostShortMsg = " to {$hostName}";
    $hostLongMsg = " deployed to {$hostName} {$hostEmoji}";


    if(has('launch_url'))
    {
        $launchUrl = get('launch_url');
        $launchContent = "\n>:rocket: <{$launchUrl}|Launch App>";
        $buildLink = "^{$launchUrl}/{$buildFilename}";
    }
    else
    {
        $launchContent = '';
        $buildDetailsUrl = '';
        $buildLink = '';
    }


    //this is the shorter message preview that users see pop up on their desktop notification
    $shortMsg = "deploy {$repoName} ({$branch}){$hostShortMsg} success";

    //this is the longer message that gets posted into the channel
    $longMessage="deployment success\n><{$repoUrl}|{$repoName}> @ <{$repoUrl}/tree/{$branch}|{$branch}>{$hostLongMsg}{$launchContent}";

    //this is the little footer message at the bottom that shows the date, with an optional link to the build file if available
    // also provides a link to the deploy revision in github
    $contextMessage = "*<!date^" . (string)time() . "^Deployed {date_long_pretty} {time_secs}{$buildLink}|" .
        (string)date(DATE_ATOM) . " local build time>* | <{$revUrl}|{$revShort}>"
    ;

    //this is the structure that the slack api expects
    $payload = [
        "text"=> $shortMsg,
        "blocks" => [
            [
                "type" => "section",
                'text' => [
                    "type"=> "mrkdwn",
                    "text" => "$longMessage"
                ]
            ],
            [
                "type" => "context",
                "elements" => [
                    [
                        "type" => "mrkdwn",
                        "text" => $contextMessage
                    ]
                ]
            ]
        ]
    ];

    //send the notification from the machine that initiated this deployment command
    $curlCommand = "curl -s -X POST -H 'Content-type: application/json' --data '" . json_encode($payload) . "' {$webhookEndpoint}";
    runLocally($curlCommand);

});
//announcements come after the success task
after('success', 'slack:notify');

desc('Notify slack of build status');
task('slack:notify_rollback', function () {
    $hostAlias = [
        "data.pointblue.org"=>[
            "name"=>"production",
            "emoji"=>":pointblue:"
        ],
        "data3.pointblue.org"=>[
            "name"=>"old aws",
            "emoji"=>":construction:"
        ],
        "data-test.pointblue.org"=>[
            "name"=>"nonprod-aws",
            "emoji"=>":construction:"
        ],
        "nonprod-php56"=>[
            "name"=>"nonprod-php56",
            "emoji"=>":construction:"
        ],
        "nonprod-php52"=>[
            "name"=>"nonprod-php52",
            "emoji"=>":construction:"
        ]
    ];

    $webhookEndpoint = get('slack_webhook_url');
    $branch = get('git_branch');

    //cannot complete function without webhook endpoint
    if(empty($webhookEndpoint))
    {
        $longMessage = 'Slack webhook not found (slack_webhook_url). Notification not sent.';
        writeln($longMessage);
        logger($longMessage);
        return;
    }

    $repoName = preg_replace('/^(git@github.com:)(.+)\.git$/', '$2', get('repository'));
    $revShort = get('git_rev_short');
    $revUrl = get('git_rev_url');
    $repoUrl = get('git_repo_url');
    $buildFilename = get('build_meta_output');
    $server = get('server');
    $configHost = $server['host'];
    //if the host in the configuration has an alias, use that. if not, just use the name set in the config
    $hostName = array_key_exists($configHost, $hostAlias) ? $hostAlias[$configHost]['name'] : $configHost;
    $hostEmoji = array_key_exists($configHost, $hostAlias) ? $hostAlias[$configHost]['emoji'] : '';
    $hostShortMsg = " to {$hostName}";
    $hostLongMsg = " rolled back on {$hostName} {$hostEmoji}";

    //this is the shorter message preview that users see pop up on their desktop notification
    $shortMsg = "deploy {$repoName} ({$branch}){$hostShortMsg} rolled back";

    //this is the longer message that gets posted into the channel
    $longMessage="deployment rolled back :back:\n><{$repoUrl}|{$repoName}> @ <{$repoUrl}/tree/{$branch}|{$branch}>{$hostLongMsg}";

    //this is the little footer message at the bottom that shows the date, with an optional link to the build file if available
    // also provides a link to the deploy revision in github
    $contextMessage = "*<!date^" . (string)time() . "^{date_long_pretty} {time_secs}|" .
        (string)date(DATE_ATOM) . " local build time>*"
    ;

    //this is the structure that the slack api expects
    $payload = [
        "text"=> $shortMsg,
        "blocks" => [
            [
                "type" => "section",
                'text' => [
                    "type"=> "mrkdwn",
                    "text" => "$longMessage"
                ]
            ],
            [
                "type" => "context",
                "elements" => [
                    [
                        "type" => "mrkdwn",
                        "text" => $contextMessage
                    ]
                ]
            ]
        ]
    ];

    //send the notification from the machine that initiated this deployment command
    $curlCommand = "curl -s -X POST -H 'Content-type: application/json' --data '" . json_encode($payload) . "' {$webhookEndpoint}";
    runLocally($curlCommand);
});
//announcements come after the success task
after('rollback', 'slack:notify_rollback');

/**
 *
 *
 * # slack:notify_failed
 *
 *
 *
 * Sends a notification to Slack via a webhook announcing that the build has failed.
 *
 *
 */
desc('Notify slack of build status');
task('slack:notify_failed', function (){

    $hostAlias = [
        "data.pointblue.org"=>[
            "name"=>"production",
            "emoji"=>":pointblue:"
        ],
        "data3.pointblue.org"=>[
            "name"=>"old aws",
            "emoji"=>":construction:"
        ],
        "data-test.pointblue.org"=>[
            "name"=>"nonprod-aws",
            "emoji"=>":construction:"
        ],
        "nonprod-php56"=>[
            "name"=>"nonprod-php56",
            "emoji"=>":construction:"
        ],
        "nonprod-php52"=>[
            "name"=>"nonprod-php52",
            "emoji"=>":construction:"
        ]
    ];

    $webhookEndpoint = get('slack_webhook_url');
    $branch = get('git_branch');

    //cannot complete function without webhook endpoint
    if(empty($webhookEndpoint))
    {
        $longMessage = 'Slack webhook not found (slack_webhook_url). Notification not sent.';
        writeln($longMessage);
        logger($longMessage);
        return;
    }

    $repoName = preg_replace('/^(git@github.com:)(.+)\.git$/', '$2', get('repository'));
    $revShort = get('git_rev_short');
    $revUrl = get('git_rev_url');
    $repoUrl = get('git_repo_url');
    $buildFilename = get('build_meta_output');
    $server = get('server');
    $configHost = $server['host'];
    //if the host in the configuration has an alias, use that. if not, just use the name set in the config
    $hostName = array_key_exists($configHost, $hostAlias) ? $hostAlias[$configHost]['name'] : $configHost;
    $hostEmoji = array_key_exists($configHost, $hostAlias) ? $hostAlias[$configHost]['emoji'] : '';
    $hostShortMsg = " to {$hostName}";
    $hostLongMsg = " did not deploy to {$hostName} {$hostEmoji}";

    //this is the shorter message preview that users see pop up on their desktop notification
    $shortMsg = "deploy {$repoName} ({$branch}){$hostShortMsg} failed";

    //this is the longer message that gets posted into the channel
    $longMessage="deployment failed :warning:\n><{$repoUrl}|{$repoName}> @ <{$repoUrl}/tree/{$branch}|{$branch}>{$hostLongMsg}";

    //this is the little footer message at the bottom that shows the date, with an optional link to the build file if available
    // also provides a link to the deploy revision in github
    $contextMessage = "*<!date^" . (string)time() . "^{date_long_pretty} {time_secs}|" .
        (string)date(DATE_ATOM) . " local build time>*"
    ;

    //this is the structure that the slack api expects
    $payload = [
        "text"=> $shortMsg,
        "blocks" => [
            [
                "type" => "section",
                'text' => [
                    "type"=> "mrkdwn",
                    "text" => "$longMessage"
                ]
            ],
            [
                "type" => "context",
                "elements" => [
                    [
                        "type" => "mrkdwn",
                        "text" => $contextMessage
                    ]
                ]
            ]
        ]
    ];

    //send the notification from the machine that initiated this deployment command
    $curlCommand = "curl -s -X POST -H 'Content-type: application/json' --data '" . json_encode($payload) . "' {$webhookEndpoint}";
    runLocally($curlCommand);

});
//announcements come after the success task
after('deploy:failed', 'slack:notify_failed');


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
    if( has('update_autoload_classmap') )
    {
        $appPaths = get('update_autoload_classmap');
    }
    else
    {
        //if there is a vendor path, automatically run this
        // it could be done smarter by only running if a deju dependency is detected (this file already has the logic for this)
        $appPaths = ['.'];
    }


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
after('deploy:vendors','deploy:update_autoload_classmap');

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

        //if the target file we're linking to doesn't exist,
        // or if the file size is zero bytes (previous tasks may have use the touch command on it)
        if(! test("[ -f \"{$envSymlink['target']}\" ]") || ( (string)run("wc -c \"{$envSymlink['target']}\" | awk '{print $1}'") === '0'))
        {
            throw new \Exception("the task deploy:symlink_envs is trying to symlink a file that does not exist or it's zero bytes: {$envSymlink['target']}" );
        }

        //create the path of the destination if it does not already exist
        // this allows use to symlink dependencies in the right spot when needed
        $destinationPath = dirname($envSymlink['destination']);
        if( ! test("[ -d \"{$destinationPath}\"]") ){
            run("mkdir -p {$destinationPath}");
        }

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
 *   - apps-authentication-common
 *   - deju_common
 *   - deju2
 *   - deju2-renew
 *   - deju3
 *
 * To do this, a structure is created
 *
 * [
 *  "base_path" => "{{deploy_path}}/apps/common",
 *  "libs" => [
 *     "deju2",
 *     "deju2-renew",
 *     "deju3"
 *   ]
 * ]
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
        "deju_common" => [
            "link_name" => 'deju',
            "current_release_path" => "{{deploy_path}}/../deju_common/current/deju"
        ],
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

    $commonSymlinks = [
        "base_path" => '',
        "libs" => []
    ];

    //This section is about the auto-discovery of deju dependencies by inspecting the composer.json file
    $composerFile = "composer.json";
    if(file_exists($composerFile))
    {

        $contents = file_get_contents($composerFile);
        $composerJson = json_decode($contents, TRUE);    //turn this into a PHP associative array

        //find deju mapped classes
        $mappedClasses = [];
        //check the autoload-dev section for deju classmaps
        if(
            array_key_exists('autoload-dev', $composerJson) &&
            array_key_exists('classmap', $composerJson['autoload-dev']) &&
            has_deju_mapped_classes($composerJson['autoload-dev']['classmap'])
        )
        {
            $mappedClasses = $composerJson['autoload-dev']['classmap'];
        }

        //check the autoload section for deju classmaps
        if(
            array_key_exists('autoload', $composerJson) &&
            array_key_exists('classmap', $composerJson['autoload']) &&
            has_deju_mapped_classes($composerJson['autoload']['classmap'])
        )
        {
            $mappedClasses = $composerJson['autoload']['classmap'];
        }

        for($i=0;$i<count($mappedClasses);$i++)
        {
            //Try to find one of the deju libraries
            // This assumes that if one deju library is found, then all other deju libraries are in the same place
            $find = '/apps\/common\/(deju2|deju2-renew|deju3)/';
            if( preg_match($find, $mappedClasses[$i]) === 1)
            {
                $commonSymlinks['base_path'] = '{{release_path}}';
                $commonSymlinks['libs'] = [
                    'deju2',
                    'deju2-renew',
                    'deju3',
                    'apps-authentication-common'
                ];
                //get the base path
                // this assumes that the path looks something like ../../apps/common
                $parts = explode('/', $mappedClasses[$i]);
                //count how many `..` paths we have
                for($j=0;$j<count($parts);$j++)
                {
                    if($parts[$j] === '..')
                    {
                        $commonSymlinks['base_path'] .= '/..';
                    }
                    else
                    {
                        break;
                    }
                }
                $commonSymlinks['base_path'] .= '/apps/common/';

                break;
            }
        }
    }

    //if cmsms and deju.php are together, deploy their common symlinks
    // this works because i assume we won't have a project with a composer file that has deju reference with a cmsms
    // site that also uses deju.php
    if( has_cmsms_deju() )
    {
        $commonSymlinks = [
            "base_path" => '{{deploy_path}}/releases/common',
            "libs" => [
                'deju_common',
                'apps-authentication-common'
            ]
        ];

    }


    //check if the necessary properties are available to create the dependency symlinks
    if(
        //if the base_path key is not defined, or if it's value is empty
        ! array_key_exists('base_path', $commonSymlinks) || empty($commonSymlinks['base_path']) ||
        //if the libs key is not defined, or if the value has a count less than 1
        ! array_key_exists('libs', $commonSymlinks) || count($commonSymlinks['libs']) < 1 )
    {
        //stop running this task, not enough configuration to create symlinks
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

desc('forces to docker deploy folder to be owned by the www-data user/group. *nonprod-php52 bug fix');
//meant to patch a "bug" with php 5.2 environment
task('deploy:fix_docker_permissions', function(){

    if( has_php52_host() ){
        //forces docker server to have www-data as owner/group of code deploy folder
        run('sudo docker exec 878aa7399781 sh -c "chown www-data:www-data -R /point_blue/deploy/"');
    }

});


desc('forces to deploy folder to be owned by the deployer user/group. *nonprod-php52 bug fix');
/**
 * our nonprod-php52 server has a permissions error that we're patching here. it's not a great solution but
 * we can't figure out why the permissions reset themselves since we started running docker.
 */
task('deploy:fix_deploy_folder_permissions', function(){

    if( has_php52_host() ){
        //forces server to have deployer as owner/group of code deploy folder
        run('sudo chown deployer:deployer -R /point_blue/deploy/');
    }

});

task('debug:say_hello', function(){
    writeln('hello, world!');
});

task('debug:has_php52_host', function(){
    $server = has_php52_host();
    var_dump($server);
});

function has_deju_mapped_classes($mappedClasses)
{
    for($i=0;$i<count($mappedClasses);$i++)
    {
        //Try to find one of the deju libraries
        // This assumes that if one deju library is found, then all other deju libraries are in the same place
        $find = '/apps\/common\/(deju2|deju2-renew|deju3)/';
        if( preg_match($find, $mappedClasses[$i]) === 1)
        {
            return true;
        }
    }

    return false;
}

/**
 * this function only works when used in a task() anonymous function
 * @return bool
 */
function has_php52_host()
{
    $server = get('server');

    //if the host name contains 'php52', return true. otherwise false
    return strpos( $server['host'], 'php52' ) !== FALSE ? TRUE : FALSE;
}




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
 * DEPRECATED
 *
 * Tasks we want to do every time for all app deployments
 *
 * Run after deploy:clear_paths task for apps *with out* laravel recipe
 * Run after artisan:optimize task for apps with laravel recipe
 *
 */
desc('DEPRECATED - Common Point Blue deployer tasks to run just before releasing the app');
task('deploy:pb_deployer_post_hook', function(){
    //noop
});



/**
 *
 *
 * TASK ORDER
 *
 * This defines the order of tasks that are run when executing the deploy task (dep deploy).
 * It is conditional on the files that are present in the project
 *
 *
 */


//define which tasks will execute
// first, divide deployments into laravel and non-laravel
if( has_laravel() )
{
    echo "loading recipe/laravel.php because composer.json present with `require.laravel/framework` property\n";
    require_once 'recipe/laravel.php';

    //since the laravel recipe we just included already defines the deploy task, we'll use 'before' and 'after'
    // functions to add tasks in the order we want

    //before running the composer install command, create the symlinks that will be needed, if any
    before('deploy:vendors', 'deploy:common_symlinks');

    //.env symlinks must be created before composer caches the config files
    // otherwise, nothing will be cached and the app will fail to run
    before('artisan:config:cache', 'deploy:symlink_envs');

    //
    // tasks that are conditional
    //

    //it is assumed that all projects with bower also have a package.json file
    if( has_bower_json() )
    {
        echo "Adding deploy:bower_components task because bower.json file is present\n";
        after('deploy:vendors', 'deploy:bower_components');
    }

    //if the project has a package.json file, then run the task that calls either `yarn install` or `npm install`.
    // the task itself decides to run yarn or npm based on the available lock files
    if( has_package_json() )
    {
        echo "Adding deploy:node_modules task because package.json file is present\n";
        after('deploy:vendors', 'deploy:node_modules');
        if( has_node_build_command() )
        {
            echo "Adding deploy:build_assets task because package.json file is present with `scripts.prod` property\n";
            after('deploy:node_modules', 'deploy:build_assets');
        }
    }
}
//elseif( ! has('custom_deploy') || ( has('custom_deploy') && get('custom_deploy') ) )
else
{
    //tasks that must run first, for all non-laravel deployments
    $taskList = [
        'deploy:fix_deploy_folder_permissions',
        'deploy:prepare',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'deploy:shared',
        'deploy:symlink_envs',
    ];

    //
    // tasks that are [mostly] conditional
    //

    if( has_cmsms_deju() )
    {
        //add dirs that must persist between deployments
        add('shared_dirs',[
            "deju/logs",
            "uploads",
        ]);
        //make sure the paths are writable
        add('writable_dirs',[
            "deju/logs",
            "deju/templates/templates_c",
            "tmp/cache",
            "tmp/templates_c",
            "uploads",
        ]);
    }

    array_push($taskList, 'deploy:writable');
    array_push($taskList, 'deploy:common_symlinks');

    //if the project has a composer.json file, then run the task that calls `composer install`
    if( has_composer_json() )
    {
        echo "Adding deploy:vendors task because composer.json file is present\n";
        array_push($taskList, 'deploy:vendors');
    }

    if( has_bower_json() )
    {
        echo "Adding deploy:bower_components task because bower.json file is present\n";
        array_push($taskList, 'deploy:bower_components');
    }

    //if the project has a package.json file, then run the task that calls either `yarn install` or `npm install`.
    // the task itself decides to run yarn or npm based on the available lock files
    if( has_package_json() )
    {
        echo "Adding deploy:node_modules task because package.json file is present\n";
        array_push($taskList, 'deploy:node_modules');
        if( has_node_build_command() )
        {
            echo "Adding deploy:build_assets task because package.json file is present with `scripts.prod` property\n";
            array_push($taskList, 'deploy:build_assets');
        }
    }

    desc('Deploy your project');
    //merge tasks that have to run last to the end of the task list
    task('deploy', array_merge($taskList, [
        'deploy:clear_paths',
        'deploy:pb_deployer_post_hook',
        'deploy:fix_docker_permissions',
        'deploy:symlink',
        'deploy:unlock',
        'cleanup',
        'success'
    ]));
}


// by default, deployments are unlocked if they fail
after('deploy:failed', 'deploy:unlock');
