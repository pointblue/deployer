<?php

namespace Deployer;


/**
 *
 * deploy:update_autoload_classmap
 *
 * Search for the deju release path and replace with `current` so it's always pointing to the correct release
 */
desc('Set deju autoload paths to current release');
task('deploy:update_autoload_classmap', function(){

    // the path's we are looking for
    // reference: https://www.tutorialspoint.com/unix/unix-regular-expressions.htm | http://www.grymoire.com/Unix/sed.html
    $find = "(deju(2|3|2-renew))\/releases\/[0-9]+";

    // the new replacement path
    $replace = "\\1\/current";

    //composer classmap file for this release
    $classmap_file = get("release_path") . "/vendor/composer/autoload_classmap.php";

    //replace command
    run("sed -i -r 's/$find/$replace/g' $classmap_file");
});


/**
 *
 * deploy:version_check
 *
 * force the user to update this package if it's old before proceeding
 *
 */
desc('Ensure the latest version of pointblue/deployer is being used');
task('deploy:version_check', function(){

    //get all the package names
    $packages = run('composer global show -l')->toString();

    //find our package in the list and pull out the relevant version parts
    $versionInfo = [];
    preg_match('/pointblue\/deployer ([\d\.]+) . ([\d\.]+)/', $packages, $versionInfo);

    // if we see out app name, but the other parts in the version message changed we'll know how it broke
    if( preg_match('/pointblue\/deployer/', $packages) && count($versionInfo) !== 3 ){
        throw new \Exception('Point Blue deployer tasks could not parse version string: ' . $packages);
    }

    //force users to update if their deploy tasks are old. this will ensure deploys are always made the same way
    if($versionInfo[2] > $versionInfo[1]){
        throw new \Exception('Point Blue deployer tasks outdated. Run: composer global update pointblue/deployer');
    }

});

//run after deploy:info to be sure it happens at the very beginning
after('deploy:version_check', 'deploy:info');

/**
 *
 * deploy:symlink_envs
 *
 * If the env_symlinks env variable is set (in deployer), a symlink will be created for each target/destination pair
 * in the array. This allows deployments to define where their own environment configs are per server.
 *
 * Be sure to use `set('use_relative_symlink', false);` in your deployer script! If not, the symlink will point to the
 * release version of the destination file, not the current version.
 *
 * Example in servers.yml:
 *
 *   myhost:
 *     user: ubuntu
 *     pem_file: ~/.ssh/pem.pem
 *     host: example.org
 *     stage: production
 *     deploy_path: /my/deploy/path
 *     branch: master
 *     env_symlinks: #link configs in shared_files to actual configs
 *       - target: "{{deploy_path}}/../environment_configs/current/aws/deju2/apps_all/build/conf/apps_all-conf-prod.php"
 *         destination: "{{deploy_path}}/shared/lib/db/apps_all/build/conf/apps_all-conf-prod.php"
 *       - target: "{{deploy_path}}/../environment_configs/current/aws/deju2/auth/build/conf/auth-conf-prod.php"
 *         destination: "{{deploy_path}}/shared/lib/db/auth/build/conf/auth-conf-prod.php"
 *
 */
desc('Symlink target/destination pair in env_symlinks array');
task('deploy:symlink_envs', function(){

    $envSymlinks = has('env_symlinks') ?  get('env_symlinks') : [];

    //create a symlink for each target / destination pair
    foreach($envSymlinks as $envSymlink){
        run("{{bin/symlink}} {$envSymlink['target']} {$envSymlink['destination']}");
    }

});


/**
 *
 * things we want all laravel apps to do after they're done building, but before release
 *
 * just add this to you project's deploy.php: after('artisan:optimize', 'deploy:pb_deployer_laravel_post_hook');
 *
 */
task('deploy:pb_deployer_post_hook_laravel', [
    'deploy:update_autoload_classmap',
]);

/**
 * Tasks we want to do every time for all app deployments
 */
task('deploy:pb_deployer_post_hook', [
    'deploy:symlink_envs'
]);
