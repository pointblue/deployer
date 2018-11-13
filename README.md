# Point Blue Deployer  

Supporting deployer tasks

## Install  

This will install the package globally   

`composer global require pointblue/deployer`  

### Configuration  

  1. Edit the `php.ini` for your command line environment at `/etc/php/VERSION/cli/php.ini` where 
  `VERSION` is something like `5.6` or `7.2`.  
  Find out your php cli version with `php --version`
  2. Find composer's global path with `composer config --list --global` and find the property \[home\].
  Append `/vendor` to this property and use this path for the next step.
  3. Update `include_path` by appending the global composer's vendor path to the end of the string 
  as `:/home/user/.config/composer/vendor` and save the file.  
  Example: `include_path = ".:/usr/share/php:/home/ubuntu/.config/composer/vendor"`.  
  
## Use  

To use the deployer tasks, just require the tasks in your `deployer.php` file at the top, but after other
required files.  

Example:
```

//deployer's common tasks
require 'recipe/common.php';

//Point Blue's deployter tasks
require 'pointblue/deployer/common-tasks.php';
```

Next, add the hooks need for the tasks to run:  

```
//right before release, call the point blue deployer hooks
after('deploy:clear_paths', 'deploy:pb_deployer_post_hook');
```

This just calls our common tasks after deployer runs the `deploy:clear_path` task.  
If you're setting up a laravel deployer script, you can also add the laravel hooks.

```
//run common tasks specific to laravel apps
after('artisan:optimize', 'deploy:pb_deployer_post_hook_laravel');
```

## Reference  

Please see the source code for task names and their use  
