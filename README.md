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
  4. Create a file named `.slack_webhook_url` in the home folder of the user that will be running the composer script.
  The contents of the file should be the url of the slack webhook that will be posted to on deploy completetion. 
  This will enable build notifications to be posted to that url. If this is not done, notifications will not be sent.
  The recommended permission for the file is 600 (`chmod 600 .slack_webhook_url`).  
  
## Use  

### deployer.php  

To use the deployer tasks, just require the tasks in your `deployer.php` file at the top, but after other
required files.  

*Example:*  
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

### cli  

To test commands without running them on the target server, use the `--pb-test` option.  
 
*Example:* 

`dep deploy:symlink_envs aws --pb-test -vvv`  

This will run the `deploy:symlink_envs` task with the aws stage settings from the `servers.yml` file and
print the symlink commands in the console instead of running them on the server. This will let you test
your configurations before deploying.

## servers.yml variables

Some additional variables can be added to your hosts definition in the `servers.yml` file.

`env_symlinks` - see `deploy:symlink_envs` in `common-tasks.php`  
`update_autoload_classmap` - see `deploy:update_autoload_classmap` in `common-tasks.php`


## Reference  

Please see the source code for task names and their use  
