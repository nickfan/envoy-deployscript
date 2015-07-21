<?php
/**
 * Envoy deployment config
 */
/**
 * application name
 */
$appname = 'mysite';
/**
 * remote server connection string
 * @example '-p 2222 vagrant@127.0.0.1'
 */
$ssh = 'user@host';
/**
 * @notice http/https protocol might be ask for password for your private repos
 *  and that will break the git clone progress,use git protocol and setup a deploy key on your server and SCM service(e.g github repo ->settings->Deploy keys) instead
 * @example 'git@localhost:user/myrepo.git'
 */
$repo = 'git@github.com:user/mysite.git';
/**
 * deployment base path
 * @example '/var/www'
 */
$deploybasepath = '/var/www';
/**
 * remote server service user(group) that run the php-fpm/nginx and the application files permissions.
 * @example 'www-data'
 */
$serviceowner = 'www-data';
