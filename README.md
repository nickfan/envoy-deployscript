# envoy-deployscript
Laravel Envoy Deployment Script

Base on [papertank/envoy-deploy](https://github.com/papertank/envoy-deploy)
Inspired by
* [papertank/envoy-deploy](https://github.com/papertank/envoy-deploy)
* [Deploying with Envoy (Cast)](https://serversforhackers.com/video/deploying-with-envoy-cast)
* [Enhancing Envoy Deployment](https://serversforhackers.com/video/enhancing-envoy-deployment)
* [An Envoyer-like deployment script using Envoy](https://iatstuti.net/blog/an-envoyer-like-deployment-script-using-envoy)
* [Rocketeer](http://rocketeer.autopergamene.eu/)

This repository includes an Envoy.blade.php script that is designed to provide a very basic "zero-downtime" deployment option using the open-source [Laravel Envoy](http://laravel.com/docs/5.1/envoy) tool.

## Requirements

This Envoy script is designed to be used with Laravel 5 projects,however you could modify for other type of projects.

Notice that your local server/machine should configured to use [SSH Key-Based Authentication](https://www.digitalocean.com/community/tutorials/how-to-configure-ssh-key-based-authentication-on-a-linux-server).

## Installation

Your must have Envoy installed using the Composer global command:

>composer global require "laravel/envoy=~1.0"

## Usage

### Setup

1. Download or clone this repository

2. then copy envoy.config.php and Envoy.blade.php to your laravel project root directory.(e.g ~/code/mysite)

3. then edit the envoy.config.php file with the ssh login, Git repository, server path for your app.
The `$deploybasepath` (server base path) should already be created in your server with right permissions(e.g owner:www-data,read/write).
You should set your website root directory (in vhost / server config) to `$deploybasepath/$appname`/current/public (e.g /var/www/mysite/current/public)

4. add `.envoydeploy/` directory to your .gitignore file in your laravel project root if you use git as your source control software.

5. you should create a .env.production file (dot env settings file ) in your laravel project root directory that will deploy to remote server
otherwise if you specify the Laravel environment (e.g development/testing) create or symbolic the corresponding env file (e.g .env.development/.env.testing)
and you could add the dot env settings file to your .gitignore file.

6. you should create an directory `extra/custom/` in your laravel project root, the deploy script will copy every directories and files in it to overwrite the files in target server.

>for example:

>you create file:

>`extra/custom/node_modules/laravel-elixir/Config.js`

>after deploy it will copy and overwrite to

>`($deployed_project_root)/node_modules/laravel-elixir/Config.js`
it's usually we use this for custom laravel-elixir config setting and etc.

### Init

When you're happy with the config, run the init task on your local machine by running the following in the repository directory

> envoy run deploy_init

You can specify the Laravel environment (for artisan:migrate command) and git branch as options

> envoy run deploy_init --branch=develop --env=development

You only need to run the init task once.

The init task creates a `.env` file in your app root path (e.g /var/www/mysite/.env )- make sure and update the environment variables appropriately.

### Deploy

Each time you want to deploy simply run the deploy task on your local machine in the repository direcory

>envoy run deploy

You can specify the Laravel environment (for artisan:migrate command) and git branch as options

>envoy run deploy --branch=develop --env=development

#### Working copy deploy

if your remote server just have too small RAM to run `composer install` on your server (e.g some cheap VPS server instance)
you could use `deploy_mix_pack` instead of `deploy` command.
>envoy run deploy_mix_pack

The **deploy_mix_pack** task assume your local working copy have the same branch same env for remote deploy repo
and you have `composer install` and optional `npm install` or `bower install` task done on your local working copy.
( you could disable that `npm install` or `bower install` by comment out that line in Envoy.blade.php if you don't need/have npm/bower installed)
then it will pack `vendor` and `node_modules` into deps.tgz and scp to the remote server to deploy to the target directory
and git clone repo at remote server and deploy links and etc.

#### Release and dependencies build and deploy from local

if your remote server don't have access to your git repos or don't have access to run `composer install`  (e.g some internal web server)
> envoy run deploy_localrepo_install --branch=master --env=production

The **deploy_localrepo_install** task will  clone repo locally and pack deps then scp to remote server and deploy links and etc.
that will ONLY require your local server have access to remote server and git server.

### Rollback
If you found your last deployment likely have some errors,you could simply run the rollback task on your local machine in the repository direcory

>envoy run rollback

notice that will only relink your *current* release to previous release,
it will NOT do the database migrate rollback.

if you wanna rollback database migration you could run BEFORE you run *rollback* task:
>envoy run database_migrate_public_rollback --branch=master --env=production

if you run *rollback* task twice ,you will got *current* release still symbolic link to last release.

## How it Works

Your `$deploybasepath` directory will look something like this.
```
	mysite/
	mysite2/
	mysite3/
```
Your `$deploybasepath/$appname` directory will look something like this after you init and then deploy.

```
	releases/release_20150717032737/
	releases/release_20150717034646/
	current -> ./releases/release_20150717034646
	shared/storage/
	tmp/
	.env
```

As you can see, the *current* directory is symlinked to the latest deployment folder

Inside one of your deployment folders looks like the following (excluded some laravel folders for space)

```
	app/
	artisan
	boostrap/
	public/index.php
	composer.json
	.env -> ../../.env
	storage -> ../../shared/storage
	vendor/
```

The deployment folder .env file and storage directory are symlinked to the parent folders in the main (parent) path.

## Feature

* You could deploy multi projects with different $appname and config settings on same target eserver.
* Because of *Laravel Envoy* **Could NOT invoke Task Macro form another Task Macro yet**,
You have to copy and paste one the block of Task Macros form (`deploy_mix_pack` | `deploy_mix_update` | `deploy_localrepo_install` | `deploy_remote_install`)
to overwrite the `deploy` Task Macro code block to change the default behavior of `deploy` command.
* To explore more feature by RTFC, and custom task as you wish in your project.

## Notice

* http/https protocol might be ask for password for your private repos
and that will break the git clone progress,
use git protocol and setup a deploy key on your server and SCM service instead
(e.g github repo ->settings->Deploy keys and set `$repo = 'git@github.com:user/mysite.git'` in your *envoy.config.php*)

* the Task `cleanup_oldreleases` sometime may couldn't clean up all old release since your project contains too many files.
and you could tweak keeps releases by change the command line `tail -n +4` 4 means keep 3 releases.

## Todo

* Make deploy command more flexible.

## Contributing

Please submit improvements and fixes :)

## Author

[Nick Fan](http://axiong.me)

