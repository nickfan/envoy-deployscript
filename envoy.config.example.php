<?php
/**
 * Envoy deployment script config file
 */
/**
 * application name
 */
$app_name = 'mysite';
/**
 * remote server connection string
 * @example 'vagrant@127.0.0.1'
 */
$connection_string = 'user@127.0.0.1';
/**
 * remote server connection port default 22 ,vagrant default 2222
 * @example 22
 */
$connection_port = 22;
/**
 * @notice http/https protocol might be ask for password for your private repos
 *  and that will break the git clone progress,use git protocol instead
 * @example 'git@localhost:user/myrepo.git'
 */
$source_repo = 'git@localhost:user/myrepo.git';
/**
 * deployment base path
 * @example '/var/www'
 */
$deploy_basepath = '/var/www';
/**
 * remote server service user(group) that run the php-fpm/nginx and the application files permissions.
 * @example 'www-data'
 */
$service_owner = 'www-data';

/**
 * pack mode local | remote
 * local : checkout code and prepare the app code package locally,then pack and rsync/scp packed files to remote and extract on remote (good for small vps but scp cost bandwidth)
 * remote : checkout code and prepare the app code package on remote server (fast for good network connection)
 */
$pack_mode = 'remote';

/**
 * deploy mode incr | link
 * incr : sync new code to current running path (if you have lot of code and resource files in your project ,you may choose this mode)
 * link : link new release path to current running path (if you want light and quick code deployment, you may choose this mode)
 */
$deploy_mode = 'link';

/**
 * number of releases keep on remote
 */
$release_keep_count = 3;

/**
 * shared sub-directories name , eg: storage
 */
$shared_subdirs = array(
    'bootstrap/cache',
    'storage',
);
/**
 * Misc. Settings
 */
$settings = array(
    // default env set
    'env_default'=>'production',
    // default branch set
    'branch_default'=>'master',
    // vcs update local workingcopy before deployment.
    'workingcopy_update'=>true,
    // depends install for local workingcopy before deployment.
    'workingcopy_deps_install'=>false,
    // use shared base app_path env file.
    'use_appbase_envfile'=>true,
    // depends install components settings.
    'deps_install_component'=>array(
        'composer'=>true,
        'npm'=>false,
        'bower'=>false,
        'gulp'=>false,
    ),
    'deps_install_command'=>array(
        'composer'=>'composer install --prefer-dist --no-scripts --no-interaction && composer dump-autoload --optimize',
        'npm'=>'npm install',
        'bower'=>'bower install',
        'gulp'=>'gulp',
    ),
    'runtime_optimize_component'=>array(
        'composer'=>true,
        'artisan'=>array(
            'optimize'=>false,
            'config_cache'=>false,
            'route_cache'=>false,
        ),
    ),
    'runtime_optimize_command'=>array(
        'composer'=>'composer dump-autoload --optimize',
        'artisan'=>array(
            'optimize'=>'php artisan clear-compiled && php artisan optimize',
            'config_cache'=>'php artisan config:cache',
            'route_cache'=>'php artisan route:cache',
        ),
    ),
    // do database migrate on deploy
    'databasemigrate_on_deploy'=>true,
    // allow extra custom files overwrite.
    'extracustomoverwrite_enable'=>false,
    // depends reinstall on remote release.
    'deps_reinstall_on_remote_release'=>true,
    // do database migrate rollback on rollback
    'databasemigraterollback_on_rollback'=>false,
    // enable custom task after deploy
    'enable_custom_task_after_deploy'=>true,
);
