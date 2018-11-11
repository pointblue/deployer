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