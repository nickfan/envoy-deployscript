@include('envoy.config.php');
@setup
    if ( ! isset($appname) ) {
        throw new Exception('App Name is not set');
    }
    if ( ! isset($ssh) ) {
        throw new Exception('SSH login username/host is not set');
    }
    if ( ! isset($repo) ) {
        throw new Exception('Git repository is not set');
    }

    if ( ! isset($serviceowner) ) {
        throw new Exception('Service Owner is not set');
    }
    if ( ! isset($deploybasepath) ) {
        throw new Exception('Path is not set');
    }
    if ( substr($deploybasepath, 0, 1) !== '/' ) {
        throw new Exception('Careful - your path does not begin with /');
    }
    $now = new DateTime();
    $dateDisplay = $now->format('Y-m-d H:i:s');
    $date = $now->format('YmdHis');
    $env = isset($env) ? $env : "production";
    $branch = isset($branch) ? $branch : "master";
    $deploybasepath = rtrim($deploybasepath, '/');
    $app_base = $deploybasepath.'/'.$appname;
    $release_dir = $app_base.'/releases';
    $app_dir = $app_base.'/current';
    $prev_dir = $app_base.'/prevrelease';
    $last_dir = $app_base.'/lastrelease';
    $shared_dir = $app_base.'/shared';
    $release = isset($release) ? $release :'release_' . date('YmdHis');
    $local_dir = getcwd();
    $local_envoydeploy_dirname = '.envoydeploy';
    $local_envoydeploy_base = $local_dir.'/'.$local_envoydeploy_dirname;
@endsetup
@servers(['local'=>'localhost','web' => $ssh])
@macro('help')
    showcmdlist
@endmacro
@macro('deploy')
    {{--deploy_localrepo_pack--}}
    show_env
    init_basedir_local
    fetch_repo_localrepo
    copy_env_localrepo
    pack_deps_local
    chdir_localrepo
    deps_extract_localrepo
    copy_custom_extra_localrepo
    artisan_optimize_localrepo
    pack_release_localrepo
    init_basedir_remote
    scp_release_to_remote
    extract_release_on_remote
    sync_shared
    update_permissions
    envfile_link
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
    notice_done
@endmacro
@macro('manscprelease')
    scp_release_to_remote
@endmacro
@macro('mandeployrelease')
    {{-- man scp release.tgz to [deploy_base]/[app]/tmp/ --}}
    {{--man scp  & deploy_localrepo_pack--}}
    show_env
    init_basedir_local
    extract_release_on_remote
    sync_shared
    update_permissions
    envfile_link
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
    notice_done
@endmacro

@macro('deploy_mix_pack', ['on' => 'local'])
    {{--deploy_mix_pack--}}
    show_env
    init_basedir_local
    update_repo_local
    copy_custom_extra_local
    artisan_optimize_local
    pack_deps_local
    init_basedir_remote
    fetch_repo_remote
    scp_deps_to_remote
    extract_deps_on_remote
    sync_shared
    update_permissions
    envfile_link
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
    artisan_reset_local
    notice_done
@endmacro
@macro('deploy_mix_update', ['on' => 'local'])
    {{--deploy_mix_update--}}
    show_env
    init_basedir_local
    update_repo_local
    deps_update_local
    copy_custom_extra_local
    artisan_optimize_local
    pack_deps_local
    init_basedir_remote
    fetch_repo_remote
    scp_deps_to_remote
    extract_deps_on_remote
    sync_shared
    update_permissions
    envfile_link
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
    artisan_reset_local
    notice_done
@endmacro
@macro('deploy_localrepo_install', ['on' => 'local'])
    {{--deploy_localrepo_install--}}
    show_env
    init_basedir_local
    fetch_repo_localrepo
    copy_env_localrepo
    chdir_localrepo
    deps_install_localrepo
    copy_custom_extra_localrepo
    artisan_optimize_localrepo
    pack_release_localrepo
    init_basedir_remote
    scp_release_to_remote
    extract_release_on_remote
    sync_shared
    update_permissions
    envfile_link
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
    notice_done
@endmacro
@macro('deploy_localrepo_pack', ['on' => 'local'])
    {{--deploy_localrepo_pack--}}
    show_env
    init_basedir_local
    fetch_repo_localrepo
    copy_env_localrepo
    pack_deps_local
    chdir_localrepo
    deps_extract_localrepo
    copy_custom_extra_localrepo
    artisan_optimize_localrepo
    pack_release_localrepo
    init_basedir_remote
    scp_release_to_remote
    extract_release_on_remote
    sync_shared
    update_permissions
    envfile_link
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
    notice_done
@endmacro
@macro('deploy_remote_install', ['on' => 'web'])
    {{--deploy_remote_install--}}
    show_env
    init_basedir_remote
    fetch_repo_remote
    sync_shared
    update_permissions
    envfile_link
    chdir_release
    deps_install_remote
    copy_custom_extra_remote
    artisan_optimize_remote
    database_migrate
    link_newrelease
    cleanup_oldreleases
    notice_done
@endmacro
@macro('deploy_init', ['on' => 'local'])
    init_basedir_local
    init_basedir_remote
    scp_env_to_remote
    link_env_on_remote
@endmacro
@macro('rollback')
    {{--database_migrate_public_rollback--}}
    link_rollback
@endmacro
@task('showcmdlist',['on' => 'local'])
    echo '----';
    echo 'deploy';
    echo 'manscprelease';
    echo 'mandeployrelease';
    echo 'deploy_mix_pack';
    echo 'deploy_localrepo_install';
    echo 'deploy_remote_install';
    echo 'deploy_init';
    echo 'rollback';
    echo 'show_env';
    echo '----';
@endtask
@task('show_env',['on' => 'web'])
    echo '...';
    echo 'Current Release Name: {{$release}}';
    echo 'Current environment is {{$env}}';
    echo 'Current branch is {{$branch}}';
    echo 'Deployment Start at {{$dateDisplay}}';
    echo '----';
@endtask
@task('init_basedir_remote',['on' => 'web'])
    [ -d {{ $release_dir }} ] || mkdir -p {{ $release_dir }};
    [ -d {{ $shared_dir }} ] || mkdir -p {{ $shared_dir }};
    [ -d {{ $shared_dir }}/storage ] || mkdir -p {{ $shared_dir }}/storage;
    [ -d {{ $app_base }}/tmp ] || mkdir -p {{ $app_base }}/tmp;
@endtask
@task('init_basedir_local',['on' => 'local'])
    [ -d {{ $local_envoydeploy_base }} ] || mkdir -p {{ $local_envoydeploy_base }};
    [ -d {{ $local_envoydeploy_base }}/deps ] || mkdir -p {{ $local_envoydeploy_base }}/deps;
    [ -d {{ $local_envoydeploy_base }}/releases ] || mkdir -p {{ $local_envoydeploy_base }}/releases;
@endtask
@task('scp_env_to_remote',['on' => 'local'])
    echo "scp env to remote...";
    [ -f {{ $local_dir }}/.env.{{ $env }} ] && scp {{ $local_dir }}/.env.{{ $env }} {{ $ssh }}:{{ $app_base }}/.env.{{ $env }};
    [ -f {{ $local_dir }}/envoy.config.{{ $env }}.php ] && scp {{ $local_dir }}/envoy.config.{{ $env }}.php {{ $ssh }}:{{ $app_base }}/envoy.config.{{ $env }}.php;
    echo "scp env to remote Done.";
@endtask
@task('link_env_on_remote',['on' => 'web'])
    echo "link env on remote...";
    [ -f {{ $app_base }}/.env.{{ $env }} ] && rm -rf {{ $app_base }}/.env;
    [ -f {{ $app_base }}/.env.{{ $env }} ] && ln -nfs {{ $app_base }}/.env.{{ $env }} {{ $app_base }}/.env;
    [ -f {{ $app_base }}/.env.{{ $env }} ] && chgrp -h {{$serviceowner}} {{ $app_base }}/.env;

    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && rm -rf {{ $app_base }}/envoy.config.php;
    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && ln -nfs {{ $app_base }}/envoy.config.{{ $env }}.php {{ $app_base }}/envoy.config.php;
    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && chgrp -h {{$serviceowner}} {{ $app_base }}/envoy.config.php;
    echo "link env on remote Done.";
@endtask
@task('fetch_repo_remote',['on' => 'web'])
    echo "Repository cloning...";
    cd {{ $release_dir }};
    git clone {{ $repo }} --branch={{ $branch }} --depth=1 {{ $release }};
    echo "Repository cloned.";
@endtask
@task('update_repo_local',['on' => 'local'])
    echo "Repository update...";
    cd {{ $local_dir }};
    git fetch origin;
    git pull;
    echo "Repository updated.";
@endtask
@task('fetch_repo_local',['on' => 'local'])
    echo "Repository pull...";
    cd {{ $local_dir }};
    git fetch origin;
    git checkout -B {{ $branch }} origin/{{ $branch }};
    git pull;
    echo "Repository pulled.";
@endtask
@task('fetch_repo_localrepo',['on' => 'local'])
    echo "Repository cloning...";
    echo {{ $local_envoydeploy_base }};
    echo {{ $appname }};
    cd {{ $local_envoydeploy_base }};
    [ -d {{ $local_envoydeploy_base }}/releases/{{ $appname }} ] && echo "exists previous repo clone,need to remove.";
    [ -d {{ $local_envoydeploy_base }}/releases/{{ $appname }} ] && rm -rf {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    git clone {{ $repo }} --branch={{ $branch }} --depth=1 {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    echo "Repository cloned.";
@endtask
@task('copy_env_localrepo',['on' => 'local'])
    echo "Repo Environment file setup";
    [ -f {{ $local_dir }}/.env.development ] && cp -af {{ $local_dir }}/.env.development {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env;
    [ -f {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env.development ] && cp -af {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env.development {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env;
    [ -f {{ $local_dir }}/.env ] && cp -af {{ $local_dir }}/.env {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env;
    [ -f {{ $local_dir }}/.env.{{ $env }} ] && cp -af {{ $local_dir }}/.env.{{ $env }} {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env;
    [ -f {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env.{{ $env }} ] && cp -af {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env.{{ $env }} {{ $local_envoydeploy_base }}/releases/{{ $appname }}/.env;
    echo "Repo Environment file setup done";
@endtask
@task('sync_shared',['on' => 'web'])
    {{--#cp -af {{ $release_dir }}/{{ $release }}/storage/* {{ $shared_dir }}/storage/;--}}
    rsync --progress -e ssh -avzh --delay-updates --exclude "*.logs" {{ $release_dir }}/{{ $release }}/storage/ {{ $shared_dir }}/storage/;
    rm -rf {{ $release_dir }}/{{ $release }}/storage;
    ln -nfs {{ $shared_dir }}/storage {{ $release_dir }}/{{ $release }}/storage;
    echo "New Release Shared directory setup";
@endtask
@task('update_permissions',['on' => 'web'])
    cd {{ $release_dir }};
    chgrp -R {{$serviceowner}} {{ $release }} {{ $shared_dir }}/storage;
    chmod -R ug+rwx {{ $release }} {{ $shared_dir }}/storage;
@endtask
@task('envfile_link',['on' => 'web'])
    [ -f {{ $release_dir }}/{{ $release }}/.env ] && rm -rf {{ $release_dir }}/{{ $release }}/.env;
    ln -nfs {{ $app_base }}/.env {{ $release_dir }}/{{ $release }}/.env;
    chgrp -h {{$serviceowner}} {{ $release_dir }}/{{ $release }}/.env;

    [ -f {{ $release_dir }}/{{ $release }}/envoy.config.php ] && rm -rf {{ $release_dir }}/{{ $release }}/envoy.config.php;
    ln -nfs {{ $app_base }}/envoy.config.php {{ $release_dir }}/{{ $release }}/envoy.config.php;
    chgrp -h {{$serviceowner}} {{ $release_dir }}/{{ $release }}/envoy.config.php;
    echo "Environment file symbolic link setup";
@endtask
@task('chdir_localrepo',['on' => 'local'])
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    echo "Change directory to {{ $local_envoydeploy_base }}/releases/{{ $appname }}";
@endtask
@task('chdir_release',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    echo "Change directory to {{ $release_dir }}/{{ $release }}";
@endtask
@task('deps_install_remote',['on' => 'web'])
    echo "Dependencies install...";
    cd {{ $release_dir }}/{{ $release }};
    composer install --prefer-dist --no-scripts --no-interaction;
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    npm install;
    bower install;
    echo "Dependencies installed.";
@endtask
@task('deps_install_local',['on' => 'local'])
    echo "Dependencies install...";
    cd {{ $local_dir }};
    composer install --prefer-dist --no-scripts --no-interaction;
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    npm install;
    bower install;
    echo "Dependencies installed.";
@endtask
@task('deps_install_localrepo',['on' => 'local'])
    echo "Dependencies install...";
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    composer install --prefer-dist --no-scripts --no-interaction;
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    {{--npm install;--}}
    {{--bower install;--}}
    echo "Dependencies installed.";
@endtask
@task('deps_extract_localrepo',['on' => 'local'])
    echo "Dependencies extract...";
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    {{--composer install --prefer-dist --no-scripts --no-interaction;--}}
    tar zxf {{ $local_envoydeploy_base }}/deps/deps.tgz
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    {{--npm install;--}}
    {{--bower install;--}}
    echo "Dependencies extracted.";
@endtask
@task('deps_update_remote',['on' => 'web'])
    echo "Dependencies update...";
    cd {{ $release_dir }}/{{ $release }};
    composer update -vv --no-scripts --no-interaction;
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    npm update;
    bower update;
    echo "Dependencies updated.";
@endtask
@task('deps_update_local',['on' => 'local'])
    echo "Dependencies update...";
    cd {{ $local_dir }};
    composer update -vv --no-scripts --no-interaction;
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    npm update;
    bower update;
    echo "Dependencies updated.";
@endtask
@task('deps_update_localrepo',['on' => 'local'])
    echo "Dependencies update...";
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    composer update -vv --no-scripts --no-interaction;
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    npm update;
    bower update;
    echo "Dependencies updated.";
@endtask
@task('copy_custom_extra_remote',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    if [ -d {{ $release_dir }}/{{ $release }}/extra/custom ]; then
        cp -af {{ $release_dir }}/{{ $release }}/extra/custom/* {{ $release_dir }}/{{ $release }}/;
    fi
@endtask
@task('copy_custom_extra_local',['on' => 'local'])
    cd {{ $local_dir }};
    if [ -d {{ $local_dir }}/extra/custom ]; then
        cp -af {{ $local_dir }}/extra/custom/* {{ $local_dir }}/;
    fi
@endtask
@task('copy_custom_extra_localrepo',['on' => 'local'])
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    if [ -d {{ $local_envoydeploy_base }}/releases/{{ $appname }}/extra/custom ]; then
        cp -af {{ $local_envoydeploy_base }}/releases/{{ $appname }}/extra/custom/* {{ $local_envoydeploy_base }}/releases/{{ $appname }}/;
    fi
@endtask
@task('artisan_optimize_remote',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    php artisan config:cache;
    php artisan route:cache;
@endtask
@task('artisan_optimize_local',['on' => 'local'])
    cd {{ $local_dir }};
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    php artisan config:cache;
    php artisan route:cache;
@endtask
@task('artisan_optimize_localrepo',['on' => 'local'])
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    php artisan config:cache;
    php artisan route:cache;
@endtask
@task('artisan_reset_local',['on' => 'local'])
    cd {{ $local_dir }};
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    php artisan config:clear;
    php artisan route:clear;
    {{--php artisan cache:clear--}}
@endtask
@task('artisan_reset_localrepo',['on' => 'local'])
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    composer dump-autoload --optimize;
    php artisan clear-compiled --env={{ $env }};
    php artisan optimize --env={{ $env }};
    php artisan config:clear;
    php artisan route:clear;
    php artisan cache:clear;
@endtask
@task('pack_deps_local',['on' => 'local'])
    echo "pack deps...";
    [ -f {{ $local_envoydeploy_base }}/deps/deps.tgz ] && rm -rf {{ $local_envoydeploy_base }}/deps/deps.tgz;
    cd {{ $local_dir }};
    [ -d {{ $local_dir }}/node_modules ] && tar czf {{ $local_envoydeploy_base }}/deps/deps.tgz vendor node_modules;
    [ ! -d {{ $local_dir }}/node_modules ] && tar czf {{ $local_envoydeploy_base }}/deps/deps.tgz vendor;
    echo "pack deps Done.";
@endtask
@task('pack_deps_localrepo',['on' => 'local'])
    echo "pack deps...";
    [ -f {{ $local_envoydeploy_base }}/deps/deps.tgz ] && rm -rf {{ $local_envoydeploy_base }}/deps/deps.tgz;
    cd {{ $local_envoydeploy_base }}/releases/{{ $appname }};
    [ -d {{ $local_envoydeploy_base }}/releases/{{ $appname }}/node_modules ] && tar czf {{ $local_envoydeploy_base }}/deps/deps.tgz vendor node_modules;
    [ ! -d {{ $local_envoydeploy_base }}/releases/{{ $appname }}/node_modules ] && tar czf {{ $local_envoydeploy_base }}/deps/deps.tgz vendor;
    echo "pack deps Done.";
@endtask
@task('pack_release_localrepo',['on' => 'local'])
    echo "pack release...";
    [ -f {{ $local_envoydeploy_base }}/releases/release.tgz ] && rm -rf {{ $local_envoydeploy_base }}/releases/release.tgz;
    cd {{ $local_envoydeploy_base }}/releases/;
    tar czf {{ $local_envoydeploy_base }}/releases/release.tgz {{ $appname }};
    echo "pack release Done.";
@endtask
@task('scp_deps_to_remote',['on' => 'local'])
    echo "scp deps to remote...";
    [ -f {{ $local_envoydeploy_base }}/deps/deps.tgz ] && scp {{ $local_envoydeploy_base }}/deps/deps.tgz {{ $ssh }}:{{ $app_base }}/tmp/deps.tgz;
    echo "scp deps to remote Done.";
@endtask
@task('scp_release_to_remote',['on' => 'local'])
    echo "scp release to remote...";
    [ -f {{ $local_envoydeploy_base }}/releases/release.tgz ] && scp {{ $local_envoydeploy_base }}/releases/release.tgz {{ $ssh }}:{{ $app_base }}/tmp/release.tgz;
    echo "scp release to remote Done.";
@endtask
@task('extract_deps_on_remote',['on' => 'web'])
    echo "extract deps on remote...";
    [ -f {{ $app_base }}/tmp/deps.tgz ] && tar zxf {{ $app_base }}/tmp/deps.tgz -C {{ $release_dir }}/{{ $release }};
    echo "extract deps on remote Done.";
@endtask
@task('extract_release_on_remote',['on' => 'web'])
    echo "extract release on remote...";
    [ -d {{ $app_base }}/tmp/{{ $appname }} ] && rm -rf {{ $app_base }}/tmp/{{ $appname }};
    [ -f {{ $app_base }}/tmp/release.tgz ] && tar zxf {{ $app_base }}/tmp/release.tgz -C {{ $app_base }}/tmp;
    [ -d {{ $release_dir }}/{{ $release }} ] && rm -rf {{ $release_dir }}/{{ $release }};
    mv {{ $app_base }}/tmp/{{ $appname }} {{ $release_dir }}/{{ $release }};
    echo "extract release on remote Done.";
@endtask
@task('artisan_keygen',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan key:generate;
@endtask
@task('artisan_down',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan down;
@endtask
@task('artisan_up',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan up;
@endtask
@task('database_migrate',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan migrate --env={{ $env }} --force --no-interaction;
@endtask
@task('database_migrate_rollback',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan migrate:rollback --env={{ $env }} --force --no-interaction;
@endtask
@task('database_migrate_public_rollback',['on' => 'web'])
    cd {{ $app_dir }};
    php artisan migrate:rollback --env={{ $env }} --force --no-interaction;
@endtask
@task('database_migrate_seed',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan migrate --seed --env={{ $env }} --force --no-interaction;
@endtask
@task('database_migrate_refresh',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan migrate:refresh --env={{ $env }} --force --no-interaction;
@endtask
@task('database_migrate_refresh_seed',['on' => 'web'])
    cd {{ $release_dir }}/{{ $release }};
    php artisan migrate:refresh --seed --env={{ $env }} --force --no-interaction;
@endtask
@task('link_newrelease',['on' => 'web'])
    echo "Deploy new Release link";
    cd {{ $app_base }};
    [ -d {{ $prev_dir }} ] && unlink {{ $prev_dir }};
    [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $prev_dir }};
    ln -nfs {{ $release_dir }}/{{ $release }} {{ $app_dir }};
    chgrp -h {{$serviceowner}} {{ $app_dir }};
    echo "Deployment ({{ $release }}) symbolic link created";
@endtask
@task('link_prevrelease',['on' => 'web'])
    cd {{ $app_base }};
    if [ ! -d {{ $prev_dir }} ]; then
        echo "noprevious link to rollback";
    else
        [ ! -d {{ $app_dir }} ] || mv {{ $app_dir }} {{ $last_dir }};
        [ ! -d {{ $prev_dir }} ] || mv {{ $prev_dir }} {{ $app_dir }};
    fi
    echo "Rollback to previous symbolic link";
@endtask
@task('link_lastrelease',['on' => 'web'])
    cd {{ $app_base }};
    if [ ! -d {{ $last_dir }} ]; then
        echo "nolast link to symbolic link";
    else
        [ ! -d {{ $app_dir }} ] || mv {{ $app_dir }} {{ $prev_dir }};
        [ ! -d {{ $last_dir }} ] || mv {{ $last_dir }} {{ $app_dir }};
    fi
    echo "Reset to last symbolic link";
@endtask
@task('link_rollback',['on' => 'web'])
    cd {{ $app_base }};
    if [ -d {{ $last_dir }} ]; then
        [ ! -d {{ $app_dir }} ] || mv {{ $app_dir }} {{ $prev_dir }};
        [ ! -d {{ $last_dir }} ] || mv {{ $last_dir }} {{ $app_dir }};
        echo "Reset to last symbolic link";
    elif [ -d {{ $prev_dir }} ] && [ ! -d {{ $last_dir }} ]; then
        [ ! -d {{ $app_dir }} ] || mv {{ $app_dir }} {{ $last_dir }};
        [ ! -d {{ $prev_dir }} ] || mv {{ $prev_dir }} {{ $app_dir }};
        echo "Rollback to previous symbolic link";
    else
        echo "noprevious link to rollback";
    fi
@endtask

@task('cleanup_oldreleases',['on' => 'web'])
    echo 'Cleanup up old releases';
    cd {{ $release_dir }};
    {{--ls -1d release_* | head -n -3 | xargs -d '\n' rm -Rf;--}}
    (ls -rd {{ $release_dir }}/*|head -n 4;ls -d {{ $release_dir }}/*)|sort|uniq -u|xargs rm -rf;
    echo "Cleanup up done.";
@endtask
@task('cleanup_tempfiles_local',['on' => 'local'])
    echo 'Cleanup up tempfiles';
    cd {{ $local_envoydeploy_base }};
    [ -f {{ $local_envoydeploy_base }}/deps/deps.tgz ] && rm -rf {{ $local_envoydeploy_base }}/deps/deps.tgz;
    [ -f {{ $local_envoydeploy_base }}/releases/release.tgz ] && rm -rf {{ $local_envoydeploy_base }}/releases/release.tgz;
    echo "Cleanup up done.";
@endtask
@task('cleanup_tempfiles_remote',['on' => 'web'])
    echo 'Cleanup up tempfiles';
    cd {{ $app_base }}/tmp;
    [ -d {{ $app_base }}/tmp/{{ $appname }} ] && rm -rf {{ $app_base }}/tmp/{{ $appname }};
    [ -f {{ $app_base }}/tmp/deps.tgz ] && rm -rf {{ $app_base }}/tmp/deps.tgz;
    [ -f {{ $app_base }}/tmp/release.tgz ] && rm -rf {{ $app_base }}/tmp/release.tgz;
    echo "Cleanup up done.";
@endtask
@task('notice_done',['on' => 'web'])
    echo "Deployment ({{ $release }}) done.";
@endtask

