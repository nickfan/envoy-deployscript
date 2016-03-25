# envoy-deployscript
Laravel Envoy Deployment Script

Base on [papertank/envoy-deploy](https://github.com/papertank/envoy-deploy)

Inspired by

* [papertank/envoy-deploy](https://github.com/papertank/envoy-deploy)
* [Deploying with Envoy (Cast)](https://serversforhackers.com/video/deploying-with-envoy-cast)
* [Enhancing Envoy Deployment](https://serversforhackers.com/video/enhancing-envoy-deployment)
* [An Envoyer-like deployment script using Envoy](https://iatstuti.net/blog/an-envoyer-like-deployment-script-using-envoy)
* [Rocketeer](http://rocketeer.autopergamene.eu/)
* [Deploy your app to DigitialOcean from Codeship using Envoy](http://laravelista.com/deploy-your-app-to-digitialocean-from-codeship-using-envoy/)


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

2. then copy envoy.config.example.php and Envoy.blade.php to your laravel project root directory.(e.g ~/code/mysite)

3. then rename(or symbolic link) envoy.config.example.php to envoy.config.php and edit the envoy.config.php file with the ssh login info, Git repository, server path for your app.
The `$deploy_basepath` (server base path) should already be created in your server with right permissions(e.g owner:www-data,read/write).
You should set your website root directory (in vhost / server config) to `$deploy_basepath/$appname`/current/public (e.g /var/www/mysite/current/public)

4. add `.envoydeploy/` directory to your .gitignore file in your laravel project root if you use git as your source control software.

5. you should create a .env.production file (dot env settings file ) in your laravel project root directory that will deploy to remote server
otherwise if you specify the Laravel environment (e.g development/testing) create or symbolic the corresponding env file (e.g .env.development/.env.testing)
and you could add the dot env settings file to your .gitignore file.

6. you could create an directory `extra/custom/` in your laravel project root, the deploy script will copy every directories and files in it to overwrite the files in target server.

>for example:

>your created file:

>`extra/custom/node_modules/laravel-elixir/Config.js`

>after deploy it will copy and overwrite to

>`($deployed_project_root)/node_modules/laravel-elixir/Config.js`

>it's usually we use this for custom laravel-elixir config setting and other staff.

### Configuration

You could tweak your **envoy.config.php** for your application situation.

#### $pack_mode
	
> **local** : checkout code and prepare the app code package locally,then pack and rsync/scp packed files to remote and extract on remote (good for small vps but scp cost bandwidth)

> **remote** : checkout code and prepare the app code package on remote server (fast for your server have good network connection)

#### $deploy_mode

> **incr** : sync new code to current running path (if you have lot of code and resource files in your project ,you may choose this mode)

> **link** : link new release path to current running path (if you want light and quick code deployment, you may choose this mode)


### Deploy-Init

When you're ready with the config, run the init task on your local machine by running the following in the repository directory

>envoy run deploy_init

You can specify the Laravel environment (for artisan:migrate command) and git branch as options

>envoy run deploy_init --branch=develop --env=development

You only need to run the init task once.

The init task creates a `.env` file in your app root path (e.g /var/www/mysite/.env )- make sure and update the environment variables appropriately.

### Deploy

Each time you want to deploy simply run the deploy task on your local machine in the repository direcory

>envoy run deploy

You can specify the Laravel environment (for artisan:migrate command) and git branch as options

>envoy run deploy --branch=develop --env=development

### Rollback
If you found your last deployment likely have some errors,you could simply run the rollback task on your local machine in the repository direcory

>envoy run rollback

notice that will only relink your *current* release to previous release,
it will NOT do the database migrate rollback.

if you wanna rollback database migration you could run **BEFORE** you run *rollback* task:
>envoy run dbrollback --branch=master --env=production

if you run *rollback* task twice ,you will got *current* release still symbolic link to last release.

## How it Works

Your `$deploy_basepath` directory will look something like this.
```
	mysite/
	mysite2/
	mysite3/
```
Your `$deploy_basepath/$app_name` directory will look something like this after you init and then deploy.

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

* You could deploy multi projects with different $app_name and config settings on same target server.

* To explore more feature by RTFC, and custom task as you wish in your project.

## Notice

* http/https protocol might be ask for password for your private repos
and that will break the git clone progress,
use git protocol and setup a deploy key on your server and SCM service instead
(e.g github repo ->settings->Deploy keys and set `$source_repo = 'git@github.com:user/mysite.git'` in your *envoy.config.php*)

* the Task `cleanupoldreleases_on_remote` sometime may couldn't clean up all old release since your project contains too many files.
and you could tweak keeps releases by change the config settings var `$release_keep_count`.


## Example Usage

you could [Deploy your app to DigitialOcean from Codeship using Envoy](http://laravelista.com/deploy-your-app-to-digitialocean-from-codeship-using-envoy/)
with this Envoy.blade.php and envoy.config.php script :

>~/.composer/vendor/bin/envoy run deploy


if your laravel project runs on a small RAM (e.g 512MB) droplet.

you could change config settings `$pack_mode = 'local';`.


## Todo

* Make backup faster.

## Contributing

Please submit improvements and fixes :)

## Author

[Nick Fan](http://axiong.me)

