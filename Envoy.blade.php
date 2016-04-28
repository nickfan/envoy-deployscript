@include('envoy.config.php');
@setup
    if ( ! isset($app_name) ) {
        throw new Exception('App Name is not set');
    }

    if ( ! isset($server_connections) ) {
        throw new Exception('Server connections(SSH login username/host is not set)');
    }
    if ( ! isset($source_repo)){
        throw new Exception('VCS Source repository is not set');
    }

    if ( ! isset($pack_mode) ) {
        throw new Exception('Pack Mode is not set');
    }
    if ( ! isset($deploy_mode) ) {
        throw new Exception('Deploy Mode is not set');
    }
    if ( ! isset($settings) ) {
        throw new Exception('Misc Settings is not set');
    }

    if ( ! isset($deploy_basepath) ) {
        throw new Exception('Base Path is not set');
    }

    if ( ! isset($release_keep_count) ) {
        throw new Exception('Release Keep Count is not set');
    }

    if ( intval($release_keep_count)<1 ) {
        throw new Exception('Release Keep Count must greater than 1');
    }

    if ( substr($deploy_basepath, 0, 1) !== '/' ) {
        throw new Exception('Careful - your base path does not begin with /');
    }

    $server_labels = [];
    $server_owners = [];
    $server_userathosts = [];
    $server_hosts = [];
    $server_ports = [];
    $server_map = [];
    foreach($server_connections as $row_label=>$row_conn_settings){
        $row_userathost = '';
        $row_host = '';
        $row_port = 22;
        $row_conn_string = '';
        $row_owner_set = false;
        $row_owner = $settings['service_owner_default'];

        if(is_array($row_conn_settings)){
            if(isset($row_conn_settings['conn'])){
                $row_conn_string = $row_conn_settings['conn'];
                if(isset($row_conn_settings['owner'])){
                    $row_owner = $row_conn_settings['owner']; $row_owner_set = true;
                }
            }elseif(isset($row_conn_settings[0])){
                $row_conn_string = $row_conn_settings[0];
                if(isset($row_conn_settings[1])){
                    $row_owner = $row_conn_settings[1]; $row_owner_set = true;
                }
            }else{
                throw Exception('server connections settings error at #'.$row_label);
            }
        }else{
            $row_conn_string = trim($row_conn_settings);
        }

        if(preg_match('/^(.*)(-p\s*[0-9]+)(.*)$/',$row_conn_string,$matches)){
            foreach($matches as $line=>$match){
                if($line>0){
                    $match = trim($match);
                    if(!empty($match)){
                        if(substr($match,0,2)=='-p'){
                            $row_port = intval(trim(substr($match,2)));
                        }else{
                            $row_userathost = $match;
                        }
                    }
                }
            }
        }else{
            $row_userathost = $row_conn_string;
        }

        $row_userandhostset = explode('@',$row_userathost);
        if(count($row_userandhostset)>1){
            if($row_owner_set==false){
                $row_owner = trim($row_userandhostset[0]);
                $row_owner_set = true;
            }
            $row_host = trim($row_userandhostset[1]);
        }else{
            $row_host = trim($row_userandhostset[0]);
        }


        $server_label = $row_label;
        is_numeric($server_label) && $server_label = $settings['server_prefix_default'].$server_label;
        $server_labels[] = $server_label;
        $server_owners[] = $row_owner;
        $server_userathosts[] = $row_userathost;
        $server_hosts[] = $row_host;
        $server_ports[] = $row_port;
        $server_map[$server_label] = $row_conn_string;
    }

    $envoy_servers = array_merge(['local'=>'localhost',],$server_map);

    $now = new DateTime();
    $dateDisplay = $now->format('Y-m-d H:i:s');
    $date = $now->format('YmdHis');
    $env = isset($env) ? $env : $settings['env_default'];
    $branch = isset($branch) ? $branch : $settings['branch_default'];
    $deploy_mode = trim(strtolower($deploy_mode));

    $deploy_basepath = rtrim($deploy_basepath, '/');
    $app_base = $deploy_basepath.'/'.$app_name;
    $source_name = 'source';
    $version_name = 'current';
    $source_dir = $app_base.'/'.$source_name;
    $build_dir = $app_base.'/builds';
    $release_dir = $app_base.'/releases';
    $version_dir = $app_base.'/versions';
    $shared_dir = $app_base.'/shared';
    $tmp_dir = $app_base.'/tmp';
    $app_dir = $app_base.'/'.$version_name;
    $releaseprev_dir_incr = $version_dir.'/releaseprev_incr';
    $releaselast_dir_incr = $version_dir.'/releaselast_incr';
    $releaseprev_dir_link = $version_dir.'/releaseprev_link';
    $releaselast_dir_link = $version_dir.'/releaselast_link';
    $releasecurrent_dir_link = $version_dir.'/releasecurrent_link';
    $buildreleaseprev_incr = $version_dir.'/buildreleaseprev_incr';
    $buildreleaselast_incr = $version_dir.'/buildreleaselast_incr';
    $buildreleaseprev_link = $version_dir.'/buildreleaseprev_link';
    $buildreleaselast_link = $version_dir.'/buildreleaselast_link';
    $buildreleasecurrent_link = $version_dir.'/buildreleasecurrent_link';

    $release = isset($release) ? $release :'release_' . date('YmdHis');

    $local_dir = getcwd();
    $localdeploy_dirname = '.envoydeploy';
    $localdeploy_base = $local_dir.'/'.$localdeploy_dirname;
    $localdeploy_source_dir = $localdeploy_base.'/'.$source_name;
    $localdeploy_tmp_dir = $localdeploy_base.'/tmp';


    $rsyncExcludePatternContext = $rsyncExcludePatternSharedDirs = $rsyncExcludePatternAddon = $rsyncExcludePatternSubmodule = '';
    $tarExcludePatternContext = $tarExcludePatternSharedDirs = $tarExcludePatternAddon = $tarExcludePatternSubmodule = '';

    if(!empty($shared_subdirs)){
        foreach($shared_subdirs as $subdirname){
            $rsyncExcludePatternSharedDirs.= ' --exclude="/'.$subdirname.'/"';
            $tarExcludePatternSharedDirs.= ' --exclude="./'.$subdirname.'/"';
        }
    }
    if(isset($exclude_addon_pattens) && !empty($exclude_addon_pattens)){
        foreach($exclude_addon_pattens as $exclude_addon_patten){
            $rsyncExcludePatternAddon.= ' --exclude="'.$exclude_addon_patten.'"';
            $tarExcludePatternAddon.= ' --exclude=".'.$exclude_addon_patten.'"';
        }
    }
    if(isset($submodule_pathmap) && !empty($submodule_pathmap)){
        foreach($submodule_pathmap as $submodule_path=>$submodule_deployedpath){
            $rsyncExcludePatternSubmodule.= ' --exclude="/'.$submodule_path.'/"';
            $tarExcludePatternSubmodule.= ' --exclude="./'.$submodule_path.'/"';
        }
    }
    $rsyncExcludePatternContext .= $rsyncExcludePatternSharedDirs;
    $rsyncExcludePatternContext .= $rsyncExcludePatternAddon;
    $rsyncExcludePatternContext .= $rsyncExcludePatternSubmodule;

    //$tarExcludePatternContext .= $tarExcludePatternSharedDirs;
    $tarExcludePatternContext .= $tarExcludePatternAddon;
    $tarExcludePatternContext .= $tarExcludePatternSubmodule;

    $spec_procs = array(
        'pack_localpack'=>array(
            //'show_env_local',
            //'init_basedir_local',
            'updaterepo_localsrc',
            'envsetup_localsrc',
            'depsinstall_localsrc',
            'extracustomoverwrite_localsrc',
            'runtimeoptimize_localsrc',
            'packrelease_localsrc',
        ),
        'rcp_localpack'=>array(
            'rcpreleasepack_to_remote',
        ),
        'extract_localpack'=>array(
            'extractreleasepack_on_remote',
        ),
        'pack_localbuildpack'=>array(
            //'show_env_local',
            //'init_basedir_local',
            'updaterepo_localsrc',
            'envsetup_localsrc',
            'depsinstall_localsrc',
            'extracustomoverwrite_localsrc',
            'runtimeoptimize_localsrc',
            'boxbuild_localsrc',
        ),
        'rcp_localbuildpack'=>array(
            'rcpbuildreleasepack_to_remote',
        ),
        'extract_localbuildpack'=>array(
            'extractbuildreleasepack_on_remote',
        ),
        'pack_remotepack'=>array(
            //'show_env_remote',
            //'init_basedir_remote',
            'updaterepo_remotesrc',
            'envsetup_remotesrc',
            'depsinstall_remotesrc',
            'extracustomoverwrite_remotesrc',
        ),
        'pack_remotebuildpack'=>array(
            //'show_env_remote',
            //'init_basedir_remote',
            'updaterepo_remotesrc',
            'envsetup_remotesrc',
            'depsinstall_remotesrc',
            'extracustomoverwrite_remotesrc',
            'runtimeoptimize_remotesrc',
            'boxbuild_remotesrc',
        ),
        'subproc_releasesetup'=>array(
            'syncshareddata_remotesrc',
            'prepare_remoterelease',
            'baseenvlink_remoterelease',
            'depsreinstall_remoterelease',
            'runtimeoptimize_remotesrc',
        ),
        'subproc_versionsetup'=>array(
            'syncreleasetoapp_version',
            'databasemigrate_version',
            'customtask_on_deploy',
            'cleanupoldreleases_on_remote',
            'cleanup_tempfiles_local',
            'cleanup_tempfiles_remote',
        ),

        'subproc_buildreleasesetup'=>array(
            'prepare_remotebuildrelease',
        ),
        'subproc_buildversionsetup'=>array(
            'syncbuildreleasetoapp_version',
            'baseenvlink_version',
            'cleanupoldbuildreleases_on_remote',
            'cleanup_tempfiles_local',
            'cleanup_tempfiles_remote',
        ),
    );
    $deploy_macro_context = '';
    $deploy_macro_context .= implode(PHP_EOL,['show_env_local','init_basedir_local','init_basedir_remote','rcp_env_to_remote','link_env_on_remote']).PHP_EOL;
    if($pack_mode=='local'){
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['pack_localpack']).PHP_EOL;
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['rcp_localpack']).PHP_EOL;
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['extract_localpack']).PHP_EOL;
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['subproc_releasesetup']).PHP_EOL;
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['subproc_versionsetup']);
    }else{
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['pack_remotepack']).PHP_EOL;
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['subproc_releasesetup']).PHP_EOL;
        $deploy_macro_context .= implode(PHP_EOL,$spec_procs['subproc_versionsetup']);
    }
    $build_macro_context = '';
    $build_macro_context .= implode(PHP_EOL,['show_env_local','init_basedir_local','init_basedir_remote','rcp_env_to_remote','link_env_on_remote']).PHP_EOL;
    if($pack_mode=='local'){
        $build_macro_context .= implode(PHP_EOL,$spec_procs['pack_localbuildpack']).PHP_EOL;
        $build_macro_context .= implode(PHP_EOL,$spec_procs['rcp_localbuildpack']).PHP_EOL;
        $build_macro_context .= implode(PHP_EOL,$spec_procs['extract_localbuildpack']).PHP_EOL;
        $build_macro_context .= implode(PHP_EOL,$spec_procs['subproc_buildreleasesetup']).PHP_EOL;
        $build_macro_context .= implode(PHP_EOL,$spec_procs['subproc_buildversionsetup']);
    }else{
        $build_macro_context .= implode(PHP_EOL,$spec_procs['pack_remotebuildpack']).PHP_EOL;
        $build_macro_context .= implode(PHP_EOL,$spec_procs['subproc_buildreleasesetup']).PHP_EOL;
        $build_macro_context .= implode(PHP_EOL,$spec_procs['subproc_buildversionsetup']);
    }

@endsetup
@servers($envoy_servers)

@task('customtask_on_deploy',['on' => $server_labels, 'parallel' => true])
    if [ {{ intval($settings['enable_custom_task_after_deploy']) }} -eq 1 ]; then
        echo 'Calling Custom Task On Deploy...';
        cd {{ $app_dir }};
        {{-- Call your custom task on deploy eg: --}}
        {{-- php artisan --}}
        echo "Custom Task done.";
    fi
@endtask

@macro('help')
    show_cmd_list
@endmacro

@macro('show_env')
    show_env_local
    show_env_remote
@endmacro

@macro('deploy_init')
    init_basedir_local
    init_basedir_remote
    rcp_env_all_to_remote
    link_env_on_remote
@endmacro

@macro('deploy')
    {{ $deploy_macro_context }}
@endmacro
@macro('build')
    {{ $build_macro_context }}
@endmacro
@macro('rollback')
    rollback_version
@endmacro
@macro('buildrollback')
    buildrollback_version
@endmacro
@macro('deploy_remotepack')
    show_env_remote
    init_basedir_local
    init_basedir_remote
    rcp_env_to_remote
    link_env_on_remote
    updaterepo_remotesrc
    envsetup_remotesrc
    depsinstall_remotesrc
    extracustomoverwrite_remotesrc
    runtimeoptimize_remotesrc
    syncshareddata_remotesrc
    prepare_remoterelease
    baseenvlink_remoterelease
    depsreinstall_remoterelease
    syncreleasetoapp_version
    databasemigrate_version
    customtask_on_deploy
    cleanupoldreleases_on_remote
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
@endmacro

@macro('deploy_localpack')
    show_env_remote
    init_basedir_local
    init_basedir_remote
    rcp_env_to_remote
    link_env_on_remote
    updaterepo_localsrc
    envsetup_localsrc
    depsinstall_localsrc
    extracustomoverwrite_localsrc
    runtimeoptimize_localsrc
    packrelease_localsrc
    rcpreleasepack_to_remote
    extractreleasepack_on_remote
    syncshareddata_remotesrc
    prepare_remoterelease
    baseenvlink_remoterelease
    depsreinstall_remoterelease
    syncreleasetoapp_version
    databasemigrate_version
    customtask_on_deploy
    cleanupoldreleases_on_remote
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
@endmacro

@macro('pack_remotepack')
    show_env_remote
    init_basedir_remote
    updaterepo_remotesrc
    envsetup_remotesrc
    depsinstall_remotesrc
    extracustomoverwrite_remotesrc
    runtimeoptimize_remotesrc
@endmacro

@macro('pack_localpack')
    show_env_local
    init_basedir_local
    updaterepo_localsrc
    envsetup_localsrc
    depsinstall_localsrc
    extracustomoverwrite_localsrc
    runtimeoptimize_localsrc
    packrelease_localsrc
@endmacro

@macro('rcp_localpack')
    rcpreleasepack_to_remote
@endmacro

@macro('extract_localpack')
    extractreleasepack_on_remote
@endmacro

@macro('mandeploy_remotesrc')
    syncshareddata_remotesrc
    prepare_remoterelease
    baseenvlink_remoterelease
    depsreinstall_remoterelease
    syncreleasetoapp_version
    databasemigrate_version
    customtask_on_deploy
    cleanupoldreleases_on_remote
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
@endmacro

@macro('pack_localbuildpack')
    show_env_local
    init_basedir_local
    updaterepo_localsrc
    envsetup_localsrc
    depsinstall_localsrc
    extracustomoverwrite_localsrc
    runtimeoptimize_localsrc
    boxbuild_localsrc
@endmacro

@macro('rcp_localbuildpack')
    rcpbuildreleasepack_to_remote
@endmacro

@macro('extract_localbuildpack')
    extractbuildreleasepack_on_remote
@endmacro

@macro('manbuild_remotesrc')
    prepare_remotebuildrelease
    syncbuildreleasetoapp_version
    baseenvlink_version
    cleanupoldbuildreleases_on_remote
    cleanup_tempfiles_local
    cleanup_tempfiles_remote
@endmacro

@macro('dbrollback')
    databasemigraterollback_version
@endmacro

@macro('appbackup')
    backupapp_version
@endmacro

@macro('databackup')
    backupshareddata_version
@endmacro

@task('show_cmd_list',['on' => 'local'])
    echo '================';
    echo '---- [common task] ----';
    echo 'show_cmd_list';
    echo 'show_env';
    echo 'deploy_init';
    echo 'deploy';
    echo 'rollback';
    echo 'build';
    echo 'buildrollback';
    echo '---- [spec task] ----';
    echo 'deploy_remotepack';
    echo 'deploy_localpack';
    echo 'pack_remotepack';
    echo 'pack_localpack';
    echo 'rcp_localpack';
    echo 'extract_localpack';
    echo 'mandeploy_remotesrc';
    echo 'pack_localbuildpack';
    echo 'rcp_localbuildpack';
    echo 'extract_localbuildpack';
    echo 'manbuild_remotesrc';
    echo '---- [addon task] ----';
    echo 'dbrollback';
    echo 'appbackup';
    echo 'databackup';
    echo '================';
@endtask

@task('show_env_local',['on' => 'local'])
    echo '...[execute at local]';
    echo 'Current Release Name: {{$release}}';
    echo 'Current environment is {{$env}}';
    echo 'Current branch is {{$branch}}';
    echo 'Current pack mode is {{$pack_mode}}';
    echo 'Current deploy mode is {{$deploy_mode}}';
    echo 'Deployment Start at {{$dateDisplay}}';
    echo '----';
@endtask

@task('show_env_remote',['on' => $server_labels, 'parallel' => true])
    echo '...[execute at remote]';
    echo 'Current Release Name: {{$release}}';
    echo 'Current environment is {{$env}}';
    echo 'Current pack mode is {{$pack_mode}}';
    echo 'Current branch is {{$branch}}';
    echo 'Current deploy mode is {{$deploy_mode}}';
    echo 'Deployment Start at {{$dateDisplay}}';
    echo '----';
@endtask

@task('init_basedir_local',['on' => 'local'])
    [ -d {{ $localdeploy_base }} ] || mkdir -p {{ $localdeploy_base }};
    {{--[ -d {{ $localdeploy_source_dir }} ] || mkdir -p {{ $localdeploy_source_dir }};--}}
    [ -d {{ $localdeploy_tmp_dir }} ] || mkdir -p {{ $localdeploy_tmp_dir }};
    [ -d {{ $localdeploy_tmp_dir }}/servers ] || mkdir -p {{ $localdeploy_tmp_dir }}/servers;
@endtask

@task('init_basedir_remote',['on' => $server_labels, 'parallel' => true])
    {{--[ -d {{ $source_dir }} ] || mkdir -p {{ $source_dir }};--}}
    [ -d {{ $build_dir }} ] || mkdir -p {{ $build_dir }};
    [ -d {{ $release_dir }} ] || mkdir -p {{ $release_dir }};
    [ -d {{ $version_dir }} ] || mkdir -p {{ $version_dir }};
    [ -d {{ $shared_dir }} ] || mkdir -p {{ $shared_dir }};
    [ -d {{ $tmp_dir }} ] || mkdir -p {{ $tmp_dir }};

    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    for subdirname in ${shareddirs};
    do
        [ -d {{ $shared_dir }}/${subdirname} ] || mkdir -p {{ $shared_dir }}/${subdirname};
    done
@endtask
@task('updatesharedpermissions_on_remote',['on' => $server_labels, 'parallel' => true])
    echo "update shared path permissions...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    for subdirname in ${shareddirs};
    do
        [ -d {{ $shared_dir }}/${subdirname} ] || mkdir -p {{ $shared_dir }}/${subdirname};
        chgrp -Rf ${service_owner} {{ $shared_dir }}/${subdirname};
        chmod -Rf ug+rwx {{ $shared_dir }}/${subdirname};
    done
    echo "update shared path permissions Done.";
@endtask
@task('rcp_env_to_remote',['on' => 'local'])
    echo "rcp env file to remote...";
    server_owners="{{ implode(' ',$server_owners) }}";
    server_hosts="{{ implode(' ',$server_hosts) }}";
    server_userathosts="{{ implode(' ',$server_userathosts) }}";
    server_ports="{{ implode(' ',$server_ports) }}";
    
    index_count=0;
    for item in $server_owners;do
        eval server_owners_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_count=0;
    for item in $server_hosts;do
        eval server_hosts_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_count=0;
    for item in $server_userathosts;do
        eval server_userathosts_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_count=0;
    for item in $server_ports;do
        eval server_ports_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_length=$((index_count-1));
    
    for step_index in $(seq 0 $index_length)
    do
        eval step_owners=\$server_owners_${step_index};
        eval step_hosts=\$server_hosts_${step_index};
        eval step_userathosts=\$server_userathosts_${step_index};
        eval step_ports=\$server_ports_${step_index};
        echo "execute for server: ${step_userathosts} ${step_ports}";
        echo "${step_owners}" > {{ $localdeploy_tmp_dir }}/servers/${step_hosts};
        scp -p${step_ports} {{ $localdeploy_tmp_dir }}/servers/${step_hosts} ${step_userathosts}:{{ $tmp_dir }}/service_owner;
        [ -f {{ $local_dir }}/.env.{{ $env }} ] && scp -p${step_ports} {{ $local_dir }}/.env.{{ $env }} ${step_userathosts}:{{ $app_base }}/.env.{{ $env }};
        [ -f {{ $local_dir }}/envoy.config.{{ $env }}.php ] && scp  -p${step_ports} {{ $local_dir }}/envoy.config.{{ $env }}.php ${step_userathosts}:{{ $app_base }}/envoy.config.{{ $env }}.php;
    done

    echo "rcp env file to remote Done.";
@endtask

@task('rcp_env_all_to_remote',['on' => 'local'])
    echo "rcp env all files to remote...";
    server_owners="{{ implode(' ',$server_owners) }}";
    server_hosts="{{ implode(' ',$server_hosts) }}";
    server_userathosts="{{ implode(' ',$server_userathosts) }}";
    server_ports="{{ implode(' ',$server_ports) }}";

    index_count=0;
    for item in $server_owners;do
        eval server_owners_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_count=0;
    for item in $server_hosts;do
        eval server_hosts_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_count=0;
    for item in $server_userathosts;do
        eval server_userathosts_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_count=0;
    for item in $server_ports;do
        eval server_ports_${index_count}=$item;
        index_count=$((index_count+1));
    done
    index_length=$((index_count-1));

    for step_index in $(seq 0 $index_length)
    do
        eval step_owners=\$server_owners_${step_index};
        eval step_hosts=\$server_hosts_${step_index};
        eval step_userathosts=\$server_userathosts_${step_index};
        eval step_ports=\$server_ports_${step_index};
        echo "execute for server: ${step_userathosts} ${step_ports}";
        echo "${step_owners}" > {{ $localdeploy_tmp_dir }}/servers/${step_hosts};
        scp -p${step_ports} {{ $localdeploy_tmp_dir }}/servers/${step_hosts} ${step_userathosts}:{{ $tmp_dir }}/service_owner;

        [ -f {{ $local_dir }}/.env.development ] && scp -p${step_ports} {{ $local_dir }}/.env.development ${step_userathosts}:{{ $app_base }}/.env.development;
        [ -f {{ $local_dir }}/.env.local ] && scp -p${step_ports} {{ $local_dir }}/.env.local ${step_userathosts}:{{ $app_base }}/.env.local;
        [ -f {{ $local_dir }}/.env.production ] && scp -p${step_ports} {{ $local_dir }}/.env.production ${step_userathosts}:{{ $app_base }}/.env.production;
        [ -f {{ $local_dir }}/.env.testing ] && scp -p${step_ports} {{ $local_dir }}/.env.testing ${step_userathosts}:{{ $app_base }}/.env.testing;
        [ -f {{ $local_dir }}/.env.{{ $env }} ] && scp -p${step_ports} {{ $local_dir }}/.env.{{ $env }} ${step_userathosts}:{{ $app_base }}/.env.{{ $env }};

        [ -f {{ $local_dir }}/envoy.config.development.php ] && scp -p${step_ports} {{ $local_dir }}/envoy.config.development.php ${step_userathosts}:{{ $app_base }}/envoy.config.development.php;
        [ -f {{ $local_dir }}/envoy.config.local.php ] && scp -p${step_ports} {{ $local_dir }}/envoy.config.local.php ${step_userathosts}:{{ $app_base }}/envoy.config.local.php;
        [ -f {{ $local_dir }}/envoy.config.production.php ] && scp -p${step_ports} {{ $local_dir }}/envoy.config.production.php ${step_userathosts}:{{ $app_base }}/envoy.config.production.php;
        [ -f {{ $local_dir }}/envoy.config.testing.php ] && scp -p${step_ports} {{ $local_dir }}/envoy.config.testing.php ${step_userathosts}:{{ $app_base }}/envoy.config.testing.php;
        [ -f {{ $local_dir }}/envoy.config.{{ $env }}.php ] && scp -p${step_ports} {{ $local_dir }}/envoy.config.{{ $env }}.php ${step_userathosts}:{{ $app_base }}/envoy.config.{{ $env }}.php;
    done

    echo "rcp env all files to remote Done.";
@endtask

@task('link_env_on_remote',['on' => $server_labels, 'parallel' => true])
    echo "link env on remote...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    [ -f {{ $app_base }}/.env.{{ $env }} ] && rm -rf {{ $app_base }}/.env;
    [ -f {{ $app_base }}/.env.{{ $env }} ] && ln -nfs {{ $app_base }}/.env.{{ $env }} {{ $app_base }}/.env;
    [ -f {{ $app_base }}/.env.{{ $env }} ] && chgrp -h ${service_owner} {{ $app_base }}/.env;

    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && rm -rf {{ $app_base }}/envoy.config.php;
    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && ln -nfs {{ $app_base }}/envoy.config.{{ $env }}.php {{ $app_base }}/envoy.config.php;
    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && chgrp -h ${service_owner} {{ $app_base }}/envoy.config.php;
    echo "link env on remote Done.";
@endtask

@task('updaterepo_workingcopy',['on' => 'local'])
    echo "Workingcopy Repository update...";
    cd {{ $local_dir }};
    git fetch origin;
    git pull;
    echo "Workingcopy Repository updated.";
@endtask
@task('depsinstall_workingcopy',['on' => 'local'])
    echo "Workingcopy Dependencies install...";
    cd {{ $local_dir }};
    if [ {{ intval($settings['deps_install_component']['composer']) }} -eq 1 ]; then
        echo "Composer install...";
        {{ $settings['deps_install_command']['composer'] }};
        {{--php artisan clear-compiled --env={{ $env }};--}}
        {{--php artisan optimize --env={{ $env }};--}}
        echo "Composer installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['npm']) }} -eq 1 ]; then
        echo "NPM install...";
        {{ $settings['deps_install_command']['npm'] }};
        echo "NPM installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['bower']) }} -eq 1 ]; then
        echo "Bower install...";
        {{ $settings['deps_install_command']['bower'] }};
        echo "Bower installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['gulp']) }} -eq 1 ]; then
        echo "gulp build...";
        {{ $settings['deps_install_command']['gulp'] }};
        echo "gulp built.";
    fi
    echo "Workingcopy Dependencies installed.";
@endtask

@task('updaterepo_localsrc',['on' => 'local'])
    echo "LocalSource Repository update...";
    if [ -d {{ $localdeploy_source_dir }} ]; then
        echo "Repository exits only update...";
        cd {{ $localdeploy_source_dir }};
        git fetch origin;
        git checkout -B {{ $branch }} origin/{{ $branch }};
        git pull origin {{ $branch }};
    else
        echo "No Previous Repository exits and cloning...";
        git clone {{ $source_repo }} --branch={{ $branch }} --depth=1 {{ $localdeploy_source_dir }};
    fi
    echo "LocalSource Repository updated.";
@endtask

@task('envsetup_localsrc',['on' => 'local'])
    echo "LocalSource Repository Environment file setup";
    [ -f {{ $local_dir }}/.env.{{ $env }} ] && cp -RLpf {{ $local_dir }}/.env.{{ $env }} {{ $localdeploy_source_dir }}/.env;
    [ -f {{ $localdeploy_source_dir }}/.env.{{ $env }} ] && cp -RLpf {{ $localdeploy_source_dir }}/.env.{{ $env }} {{ $localdeploy_source_dir }}//.env;
    echo "LocalSource Repository Environment file setup done";
@endtask
@task('depsinstall_localsrc',['on' => 'local'])
    echo "LocalSource Dependencies install...";
    cd {{ $localdeploy_source_dir }};
    if [ {{ intval($settings['deps_install_component']['composer']) }} -eq 1 ]; then
        echo "Composer install...";
        {{ $settings['deps_install_command']['composer'] }};
        echo "Composer installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['npm']) }} -eq 1 ]; then
        echo "NPM install...";
        {{ $settings['deps_install_command']['npm'] }};
        echo "NPM installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['bower']) }} -eq 1 ]; then
        echo "Bower install...";
        {{ $settings['deps_install_command']['bower'] }};
        echo "Bower installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['gulp']) }} -eq 1 ]; then
        echo "gulp build...";
        {{ $settings['deps_install_command']['gulp'] }};
        echo "gulp built.";
    fi
    echo "LocalSource Dependencies installed.";
@endtask

@task('extracustomoverwrite_localsrc',['on' => 'local'])
    if [ {{ intval($settings['extracustomoverwrite_enable']) }} -eq 1 ]; then
        echo "LocalSource Extra custom files overwriting...";
        cd {{ $localdeploy_source_dir }};
        if [ -d {{ $localdeploy_source_dir }}/extra/custom ]; then
            cp -af {{ $localdeploy_source_dir }}/extra/custom/* {{ $localdeploy_source_dir }}/;
        fi
        echo "LocalSource Extra custom files overwrote.";
    fi
@endtask
@task('runtimeoptimize_localsrc',['on' => 'local'])
    echo "LocalSource Runtime optimize...";
    cd {{ $localdeploy_source_dir }};
    if [ {{ intval($settings['runtime_optimize_component']['composer']) }} -eq 1 ]; then
        echo "Composer optimize...";
        {{ $settings['runtime_optimize_command']['composer'] }};
        echo "Composer optimized.";
    fi
    if [ {{ intval($settings['runtime_optimize_component']['artisan']['optimize']) }} -eq 1 ]; then
        echo "artisan optimize...";
        {{ $settings['runtime_optimize_command']['artisan']['optimize'] }};
        echo "artisan optimized.";
    fi
    if [ {{ intval($settings['runtime_optimize_component']['artisan']['config_cache']) }} -eq 1 ]; then
        echo "artisan config:cache...";
        {{ $settings['runtime_optimize_command']['artisan']['config_cache'] }};
        echo "artisan config:cache done.";
    fi
    if [ {{ intval($settings['runtime_optimize_component']['artisan']['route_cache']) }} -eq 1 ]; then
        echo "artisan route:cache...";
        {{ $settings['runtime_optimize_command']['artisan']['route_cache'] }};
        echo "artisan route:cache done.";
    fi
    echo "LocalSource Runtime optimized.";
@endtask
@task('packrelease_localsrc',['on' => 'local'])
    echo "LocalSource Pack release...";
    [ -f {{ $localdeploy_tmp_dir }}/release.tgz ] && rm -rf {{ $localdeploy_tmp_dir }}/release.tgz;
    cd {{ $localdeploy_base }}/;
    tar {{ $tarExcludePatternContext }} -czf {{ $localdeploy_tmp_dir }}/release.tgz {{ $source_name }};
    echo "LocalSource Pack release Done.";
@endtask
@task('rcpreleasepack_to_remote',['on' => 'local'])
    echo "rcp localpack release to remote...";
    if [ -f {{ $localdeploy_tmp_dir }}/release.tgz ]; then
        server_userathosts="{{ implode(' ',$server_userathosts) }}";
        server_ports="{{ implode(' ',$server_ports) }}";

        index_count=0;
        for item in $server_userathosts;do
            eval server_userathosts_${index_count}=$item;
            index_count=$((index_count+1));
        done
        index_count=0;
        for item in $server_ports;do
            eval server_ports_${index_count}=$item;
            index_count=$((index_count+1));
        done
        index_length=$((index_count-1));

        for step_index in $(seq 0 $index_length)
        do
            eval step_userathosts=\$server_userathosts_${step_index};
            eval step_ports=\$server_ports_${step_index};

            echo "execute for server: ${step_userathosts} ${step_ports}";
            rsync -avz --progress --port ${step_ports} {{ $localdeploy_tmp_dir }}/release.tgz ${step_userathosts}:{{ $tmp_dir }}/;
        done
    else
        echo "localpack release NOT EXISTS.";
        echo "Pass [Ctrl-c] to quit.";
        while true; do sleep 100;done;
        exit 1;
    fi
    echo "rcp localpack release to remote Done.";
@endtask
@task('rcpbuildreleasepack_to_remote',['on' => 'local'])
    echo "rcp localpack build release to remote...";
    if [ -f {{ $localdeploy_source_dir }}/{{ $app_name }}.phar ]; then
        server_userathosts="{{ implode(' ',$server_userathosts) }}";
        server_ports="{{ implode(' ',$server_ports) }}";

        index_count=0;
        for item in $server_userathosts;do
            eval server_userathosts_${index_count}=$item;
            index_count=$((index_count+1));
        done
        index_count=0;
        for item in $server_ports;do
            eval server_ports_${index_count}=$item;
            index_count=$((index_count+1));
        done
        index_length=$((index_count-1));

        for step_index in $(seq 0 $index_length)
        do
            eval step_userathosts=\$server_userathosts_${step_index};
            eval step_ports=\$server_ports_${step_index};

            echo "execute for server: ${step_userathosts} ${step_ports}";
            rsync -avz --progress --port ${step_ports} {{ $localdeploy_source_dir }}/{{ $app_name }}.phar ${step_userathosts}:{{ $tmp_dir }}/;
        done
    else
        echo "localpack build release NOT EXISTS.";
        echo "Pass [Ctrl-c] to quit.";
        while true; do sleep 100;done;
        exit 1;
    fi
    echo "rcp localpack build release to remote Done.";
@endtask

@task('extractreleasepack_on_remote',['on' => $server_labels, 'parallel' => true])
    echo "extract pack release on remote...";
    if [ -f {{ $tmp_dir }}/release.tgz ]; then
        [ -d {{ $tmp_dir }}/{{ $source_name }} ] && rm -rf {{ $tmp_dir }}/{{ $source_name }};
        tar zxf {{ $tmp_dir }}/release.tgz -C {{ $tmp_dir }};
        if [ -d {{ $tmp_dir }}/{{ $source_name }} ]; then
            if [ -d {{ $source_dir }} ]; then
                echo "Previous Remote Source Dir Exists,Moving.";
                [ -d {{ $app_base }}/source_prev ] && rm -rf {{ $app_base }}/source_prev;
                mv {{ $source_dir }} {{ $app_base }}/source_prev;
                mv {{ $tmp_dir }}/{{ $source_name }} {{ $source_dir }};
            else
                mv {{ $tmp_dir }}/{{ $source_name }} {{ $source_dir }};
            fi
        else
            echo "extract pack release on remote ERROR.";
            echo "Pass [Ctrl-c] to quit.";
            while true; do sleep 100;done;
            exit 1;
        fi
    else
        echo "pack release NOT EXISTS.";
        echo "Pass [Ctrl-c] to quit.";
        while true; do sleep 100;done;
        exit 1;
    fi
    echo "extract pack release on remote Done.";
@endtask

@task('extractbuildreleasepack_on_remote',['on' => $server_labels, 'parallel' => true])
    echo "extract pack build release on remote...";
    if [ -f {{ $tmp_dir }}/{{ $app_name }}.phar ]; then
        [ -f {{ $source_dir }}/{{ $app_name }}.phar ] && rm -rf {{ $source_dir }}/{{ $app_name }}.phar;
        mv {{ $tmp_dir }}/{{ $app_name }}.phar {{ $tmp_dir }}/{{ $app_name }}.phar;
    else
        echo "pack build release NOT EXISTS.";
        echo "Pass [Ctrl-c] to quit.";
        while true; do sleep 100;done;
        exit 1;
    fi
    echo "extract pack build release on remote Done.";
@endtask

@task('boxbuild_workingcopy',['on' => 'local'])
    echo "Workingcopy Box Build...";
    cd {{ $local_dir }};
    [ -f {{ $local_dir }}/{{ $app_name }}.phar ] && rm -rf {{ $local_dir }}/{{ $app_name }}.phar;
    $HOME/.composer/vendor/bin/box build -vvv -c {{ $local_dir }}/box.json;
    echo "Workingcopy Box Build Done.";
@endtask

@task('boxbuild_localsrc',['on' => 'local'])
    echo "LocalSource Box Build...";
    cd {{ $localdeploy_source_dir }};
    [ -f {{ $localdeploy_source_dir }}/{{ $app_name }}.phar ] && rm -rf {{ $localdeploy_source_dir }}/{{ $app_name }}.phar;
    $HOME/.composer/vendor/bin/box build -vvv -c {{ $localdeploy_source_dir }}/box.json;
    echo "LocalSource Box Build Done.";
@endtask

@task('boxbuild_remotesrc',['on' => $server_labels, 'parallel' => true])
    echo "RemoteSource Box Build...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    cd {{ $source_dir }};
    [ -f {{ $source_dir }}/{{ $app_name }}.phar ] && rm -rf {{ $source_dir }}/{{ $app_name }}.phar;
    $HOME/.composer/vendor/bin/box build -vvv -c {{ $source_dir }}/box.json;
    echo "RemoteSource Box Build Done.";
@endtask

@task('updaterepo_remotesrc',['on' => $server_labels, 'parallel' => true])
    echo "RemoteSource Repository update...";
    if [ -d {{ $source_dir }} ]; then
        echo "Repository exits only update...";
        cd {{ $source_dir }};
        git fetch origin;
        git checkout -B {{ $branch }} origin/{{ $branch }};
        git pull origin {{ $branch }};
    else
        echo "No Previous Repository exits and cloning...";
        git clone {{ $source_repo }} --branch={{ $branch }} --depth=1 {{ $source_dir }};
    fi
    echo "RemoteSource Repository updated.";
@endtask

@task('envsetup_remotesrc',['on' => $server_labels, 'parallel' => true])
    echo "RemoteSource Repository Environment file setup";
    [ -f {{ $source_dir }}/.env.{{ $env }} ] && cp -RLpf {{ $source_dir }}/.env.{{ $env }} {{ $source_dir }}/.env;
    [ -f {{ $app_base }}/.env.{{ $env }} ] && cp -RLpf {{ $app_base }}/.env.{{ $env }} {{ $source_dir }}//.env;
    echo "RemoteSource Repository Environment file setup done";
@endtask

@task('depsinstall_remotesrc',['on' => $server_labels, 'parallel' => true])
    echo "RemoteSource Dependencies install...";
    cd {{ $source_dir }};
    if [ {{ intval($settings['deps_install_component']['composer']) }} -eq 1 ]; then
        echo "Composer install...";
        {{ $settings['deps_install_command']['composer'] }};
        echo "Composer installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['npm']) }} -eq 1 ]; then
        echo "NPM install...";
        {{ $settings['deps_install_command']['npm'] }};
        echo "NPM installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['bower']) }} -eq 1 ]; then
        echo "Bower install...";
        {{ $settings['deps_install_command']['bower'] }};
        echo "Bower installed.";
    fi
    if [ {{ intval($settings['deps_install_component']['gulp']) }} -eq 1 ]; then
        echo "gulp build...";
        {{ $settings['deps_install_command']['gulp'] }};
        echo "gulp built.";
    fi
    echo "RemoteSource Dependencies installed.";
@endtask

@task('extracustomoverwrite_remotesrc',['on' => $server_labels, 'parallel' => true])
    if [ {{ intval($settings['extracustomoverwrite_enable']) }} -eq 1 ]; then
        echo "RemoteSource Extra custom files overwriting...";
        cd {{ $source_dir }};
        if [ -d {{ $source_dir }}/extra/custom ]; then
            cp -af {{ $source_dir }}/extra/custom/* {{ $source_dir }}/;
        fi
        echo "RemoteSource Extra custom files overwrote.";
    fi
@endtask

@task('runtimeoptimize_remotesrc',['on' => $server_labels, 'parallel' => true])
    echo "RemoteSource Runtime optimize...";
    cd {{ $release_dir }}/{{ $release }};
    if [ {{ intval($settings['runtime_optimize_component']['composer']) }} -eq 1 ]; then
        echo "Composer optimize...";
        {{ $settings['runtime_optimize_command']['composer'] }};
        echo "Composer optimized.";
    fi
    if [ {{ intval($settings['runtime_optimize_component']['artisan']['optimize']) }} -eq 1 ]; then
        echo "artisan optimize...";
        {{ $settings['runtime_optimize_command']['artisan']['optimize'] }};
        echo "artisan optimized.";
    fi
    if [ {{ intval($settings['runtime_optimize_component']['artisan']['config_cache']) }} -eq 1 ]; then
        echo "artisan config:cache...";
        {{ $settings['runtime_optimize_command']['artisan']['config_cache'] }};
        echo "artisan config:cache done.";
    fi
    if [ {{ intval($settings['runtime_optimize_component']['artisan']['route_cache']) }} -eq 1 ]; then
        echo "artisan route:cache...";
        {{ $settings['runtime_optimize_command']['artisan']['route_cache'] }};
        echo "artisan route:cache done.";
    fi
    echo "RemoteSource Runtime optimized.";
@endtask

@task('syncshareddata_remotesrc',['on' => $server_labels, 'parallel' => true])
    echo "RemoteSource Sync SharedData...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    for subdirname in ${shareddirs};
    do
        [ -d {{ $shared_dir }}/${subdirname} ] || mkdir -p {{ $shared_dir }}/${subdirname};
        #chgrp -Rf ${service_owner} {{ $shared_dir }}/${subdirname};
        #chmod -Rf ug+rwx {{ $shared_dir }}/${subdirname};
        rsync --progress -e ssh -avzh --delay-updates --exclude "*.logs" {{ $source_dir }}/${subdirname}/ {{ $shared_dir }}/${subdirname}/;
        chgrp -Rf ${service_owner} {{ $shared_dir }}/${subdirname};
        chmod -Rf ug+rwx {{ $shared_dir }}/${subdirname};
    done
    echo "RemoteSource Sync SharedData Done.";
@endtask

@task('prepare_remoterelease',['on' => $server_labels, 'parallel' => true])
    echo "RemoteRelease Prepare...";
    rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $source_dir }}/ {{ $release_dir }}/{{ $release }}/;

    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    for subdirname in ${shareddirs};
    do
        if [ -e {{ $release_dir }}/{{ $release }}/${subdirname} ]; then
            if [ ! -L {{ $release_dir }}/{{ $release }}/${subdirname} ]; then
                mkdir -p {{ $release_dir }}/{{ $release }}/${subdirname};
                rm -rf {{ $release_dir }}/{{ $release }}/${subdirname};
                ln -nfs {{ $shared_dir }}/${subdirname} {{ $release_dir }}/{{ $release }}/${subdirname};
            fi
        else
            mkdir -p {{ $release_dir }}/{{ $release }}/${subdirname};
            rm -rf {{ $release_dir }}/{{ $release }}/${subdirname};
            ln -nfs {{ $shared_dir }}/${subdirname} {{ $release_dir }}/{{ $release }}/${subdirname};
        fi
        chgrp -f ${service_owner} {{ $release_dir }}/{{ $release }}/${subdirname};
        chmod -f ug+rwx {{ $release_dir }}/{{ $release }}/${subdirname};
    done
    echo "RemoteRelease Prepare Done.";
@endtask

@task('prepare_remotebuildrelease',['on' => $server_labels, 'parallel' => true])
    echo "RemoteBuildRelease Prepare...";
    if [ -f {{ $source_dir }}/{{ $app_name }}.phar ]; then
        cp -af {{ $source_dir }}/{{ $app_name }}.phar {{ $build_dir }}/{{ $release }}.phar;
    else
        echo "remote build release NOT EXISTS.";
        echo "Pass [Ctrl-c] to quit.";
        while true; do sleep 100;done;
        exit 1;
    fi

    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    chgrp -Rf ${service_owner} {{ $build_dir }}/{{ $release }}.phar;
    chmod -Rf ug+rwx {{ $build_dir }}/{{ $release }}.phar;

    echo "RemoteBuildRelease Prepare Done.";
@endtask

@task('baseenvlink_remoterelease',['on' => $server_labels, 'parallel' => true])
    echo "RemoteRelease Environment file setup...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    if [ {{ intval($settings['use_appbase_envfile']) }} -eq 1 ]; then
        [ -f {{ $release_dir }}/{{ $release }}/.env ] && rm -rf {{ $release_dir }}/{{ $release }}/.env;
        [ -f {{ $app_base }}/.env ] && ln -nfs {{ $app_base }}/.env {{ $release_dir }}/{{ $release }}/.env;
        [ -f {{ $app_base }}/.env.{{ $env }} ] && ln -nfs {{ $app_base }}/.env.{{ $env }} {{ $release_dir }}/{{ $release }}/.env;
        [ -f {{ $release_dir }}/{{ $release }}/.env ] && chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/.env;

        [ -f {{ $release_dir }}/{{ $release }}/envoy.config.php ] && rm -rf {{ $release_dir }}/{{ $release }}/envoy.config.php;
        [ -f {{ $app_base }}/envoy.config.php ] && ln -nfs {{ $app_base }}/envoy.config.php {{ $release_dir }}/{{ $release }}/envoy.config.php;
        [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && ln -nfs {{ $app_base }}/envoy.config.{{ $env }}.php {{ $release_dir }}/{{ $release }}/envoy.config.php;
        [ -f {{ $release_dir }}/{{ $release }}/envoy.config.php ] && chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/envoy.config.php;
    else
        if [ ! -f {{ $release_dir }}/{{ $release }}/.env ]; then
            [ -f {{ $app_base }}/.env ] && ln -nfs {{ $app_base }}/.env {{ $release_dir }}/{{ $release }}/.env;
            [ -f {{ $app_base }}/.env.{{ $env }} ] && ln -nfs {{ $app_base }}/.env.{{ $env }} {{ $release_dir }}/{{ $release }}/.env;
            [ -f {{ $release_dir }}/{{ $release }}/.env ] && chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/.env;
        else
            chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/.env;
        fi
        if [ ! -f {{ $release_dir }}/{{ $release }}/envoy.config.php ]; then
            [ -f {{ $app_base }}/envoy.config.php ] && ln -nfs {{ $app_base }}/envoy.config.php {{ $release_dir }}/{{ $release }}/envoy.config.php;
            [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && ln -nfs {{ $app_base }}/envoy.config.{{ $env }}.php {{ $release_dir }}/{{ $release }}/envoy.config.php;
            [ -f {{ $release_dir }}/{{ $release }}/envoy.config.php ] && chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/envoy.config.php;
        else
            chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/envoy.config.php;
        fi
    fi
    echo "RemoteRelease Environment file setup Done.";
@endtask

@task('baseenvlink_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Environment file setup...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    [ -f {{ $app_base }}/.env ] && ln -nfs {{ $app_base }}/.env {{ $app_dir }}/.env;
    [ -f {{ $app_base }}/.env.{{ $env }} ] && ln -nfs {{ $app_base }}/.env.{{ $env }} {{ $app_dir }}/.env;
    
    [ -f {{ $app_base }}/envoy.config.php ] && ln -nfs {{ $app_base }}/envoy.config.php {{ $app_dir }}/envoy.config.php;
    [ -f {{ $app_base }}/envoy.config.{{ $env }}.php ] && ln -nfs {{ $app_base }}/envoy.config.{{ $env }}.php {{ $app_dir }}/envoy.config.php;
    chgrp -h ${service_owner} {{ $app_dir }}/.env;
    chgrp -h ${service_owner} {{ $app_dir }}/envoy.config.php;
    echo "RemoteVersion Environment file setup Done.";
@endtask

@task('depsreinstall_remoterelease',['on' => $server_labels, 'parallel' => true])
    if [ {{ intval($settings['deps_reinstall_on_remote_release']) }} -eq 1 ]; then
        echo "RemoteRelease Dependencies reinstall...";
        cd {{ $release_dir }}/{{ $release }};
        if [ {{ intval($settings['deps_install_component']['composer']) }} -eq 1 ]; then
            echo "Composer install...";
            {{ $settings['deps_install_command']['composer'] }};
            echo "Composer installed.";
        fi
        if [ {{ intval($settings['deps_install_component']['npm']) }} -eq 1 ]; then
            echo "NPM install...";
            {{ $settings['deps_install_command']['npm'] }};
            echo "NPM installed.";
        fi
        if [ {{ intval($settings['deps_install_component']['bower']) }} -eq 1 ]; then
            echo "Bower install...";
            {{ $settings['deps_install_command']['bower'] }};
            echo "Bower installed.";
        fi
        if [ {{ intval($settings['deps_install_component']['gulp']) }} -eq 1 ]; then
            echo "gulp build...";
            {{ $settings['deps_install_command']['gulp'] }};
            echo "gulp built.";
        fi

        if [ {{ intval($settings['runtime_optimize_component']['composer']) }} -eq 1 ]; then
            echo "Composer optimize...";
            {{ $settings['runtime_optimize_command']['composer'] }};
            echo "Composer optimized.";
        fi
        if [ {{ intval($settings['runtime_optimize_component']['artisan']['optimize']) }} -eq 1 ]; then
            echo "artisan optimize...";
            {{ $settings['runtime_optimize_command']['artisan']['optimize'] }};
            echo "artisan optimized.";
        fi
        if [ {{ intval($settings['runtime_optimize_component']['artisan']['config_cache']) }} -eq 1 ]; then
            echo "artisan config:cache...";
            {{ $settings['runtime_optimize_command']['artisan']['config_cache'] }};
            echo "artisan config:cache done.";
        fi
        if [ {{ intval($settings['runtime_optimize_component']['artisan']['route_cache']) }} -eq 1 ]; then
            echo "artisan route:cache...";
            {{ $settings['runtime_optimize_command']['artisan']['route_cache'] }};
            echo "artisan route:cache done.";
        fi
        echo "RemoteRelease Dependencies reinstall Done.";
    fi
@endtask

@task('syncreleasetoapp_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Sync Release to App...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi

    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    if [ {{ intval($deploy_mode=='incr') }} -eq 1 ]; then
        {{-- incr mode--}}
        [ -L {{ $releaseprev_dir_link }} ] && unlink {{ $releaseprev_dir_link }};
        if [ -e {{ $app_dir }} ]; then
            {{-- prev appdir exists --}}
            {{--create incr mode prev backup--}}
            rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/" {{ $rsyncExcludePatternContext }} {{ $app_dir }}/ {{ $releaseprev_dir_incr }}/;
            for subdirname in ${shareddirs};
            do
                if [ -e {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                    if [ ! -L {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                        mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                        rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                        ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                    fi
                else
                    mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                    rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                    ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                fi
                chgrp -f ${service_owner} {{ $releaseprev_dir_incr }}/${subdirname};
                chmod -f ug+rwx {{ $releaseprev_dir_incr }}/${subdirname};
            done
            if [ -L {{ $app_dir }} ]; then
                {{--prev appdir is link mode--}}
                mv {{ $app_dir }} {{ $releaseprev_dir_link }};
            fi
        fi

        rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $release_dir }}/{{ $release }}/ {{ $app_dir }}/;

        echo "RSYNC-src to app DONE";

        for subdirname in ${shareddirs};
        do
            if [ -e {{ $app_dir }}/${subdirname} ]; then
                if [ ! -L {{ $app_dir }}/${subdirname} ]; then
                    mkdir -p {{ $app_dir }}/${subdirname};
                    rm -rf {{ $app_dir }}/${subdirname};
                    ln -nfs {{ $shared_dir }}/${subdirname} {{ $app_dir }}/${subdirname};
                fi
            else
                mkdir -p {{ $app_dir }}/${subdirname};
                rm -rf {{ $app_dir }}/${subdirname};
                ln -nfs {{ $shared_dir }}/${subdirname} {{ $app_dir }}/${subdirname};
            fi
            chgrp -f ${service_owner} {{ $app_dir }}/${subdirname};
            chmod -f ug+rwx {{ $app_dir }}/${subdirname};
        done
        if [ -e {{ $version_dir }}/release_name_current ]; then
            lastreleasevalue=`cat {{ $version_dir }}/release_name_current`;
            ln -nfs {{ $release_dir }}/$lastreleasevalue {{ $releaseprev_dir_link }};
            cp -af {{ $version_dir }}/release_name_current {{ $version_dir }}/release_name_prev;
        fi
        echo "{{ $release }}" > {{ $version_dir }}/release_name_current;
        [ -d {{ $releaselast_dir_incr }} ] && rm -rf {{ $releaselast_dir_incr }};
        [ -d {{ $releaselast_dir_link }} ] && unlink {{ $releaselast_dir_link }};
        [ -f {{ $version_dir }}/release_name_last ] && unlink {{ $version_dir }}/release_name_last;
        ln -nfs {{ $release_dir }}/{{ $release }} {{ $releasecurrent_dir_link }};
    else
        {{-- link mode--}}
        [ -L {{ $releaseprev_dir_link }} ] && unlink {{ $releaseprev_dir_link }};

        if [ -e {{ $app_dir }} ]; then
            {{-- prev appdir exists --}}
            if [ -L {{ $app_dir }} ]; then
                {{--prev appdir is link mode--}}
                {{--create incr mode prev backup--}}
                rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $app_dir }}/ {{ $releaseprev_dir_incr }}/;
                for subdirname in ${shareddirs};
                do
                    if [ -e {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                        if [ ! -L {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                            mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                            rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                            ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                        fi
                    else
                        mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                        rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                        ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                    fi
                    chgrp -f ${service_owner} {{ $releaseprev_dir_incr }}/${subdirname};
                    chmod -f ug+rwx {{ $releaseprev_dir_incr }}/${subdirname};
                done
                mv {{ $app_dir }} {{ $releaseprev_dir_link }};
            else
                [ -d {{ $releaseprev_dir_incr }} ] &&  rm -rf {{ $releaseprev_dir_incr }};
                mv {{ $app_dir }} {{ $releaseprev_dir_incr }};
            fi
        fi
        ln -nfs {{ $release_dir }}/{{ $release }} {{ $app_dir }};
        if [ -e {{ $version_dir }}/release_name_current ]; then
            lastreleasevalue=`cat {{ $version_dir }}/release_name_current`;
            ln -nfs {{ $release_dir }}/$lastreleasevalue {{ $releaseprev_dir_link }};
            cp -af {{ $version_dir }}/release_name_current {{ $version_dir }}/release_name_prev;
        fi
        echo "{{ $release }}" > {{ $version_dir }}/release_name_current;
        [ -d {{ $releaselast_dir_incr }} ] && rm -rf {{ $releaselast_dir_incr }};
        [ -d {{ $releaselast_dir_link }} ] && unlink {{ $releaselast_dir_link }};
        [ -f {{ $version_dir }}/release_name_last ] && unlink {{ $version_dir }}/release_name_last;
        ln -nfs {{ $release_dir }}/{{ $release }} {{ $releasecurrent_dir_link }};
    fi
    chgrp -h ${service_owner} {{ $app_dir }};

    if [ {{ intval(isset($submodule_pathmap) && !empty($submodule_pathmap)) }} -eq 1 ]; then
        echo "Submodule Symbolic Link...";
        submod_subdirs="{{ implode(' ',array_keys($submodule_pathmap)) }}";
        submod_deppaths="{{ implode(' ',array_values($submodule_pathmap)) }}";

        index_count=0;
        for item in $submod_subdirs;do
            eval submod_subdirs_${index_count}=$item;
            index_count=$((index_count+1));
        done
        index_count=0;
        for item in $submod_deppaths;do
            eval submod_deppaths_${index_count}=$item;
            index_count=$((index_count+1));
        done
        submod_index_length=$((index_count-1));
        for step_index in $(seq 0 $submod_index_length)
        do
            eval step_submod_subdirs=\$submod_subdirs_${step_index};
            eval step_submod_deppaths=\$submod_deppaths_${step_index};
            echo "SUBMODULES SUBDIR : ${step_submod_subdirs}";
            echo "SUBMODULES DEPLOYEDPATH : ${step_submod_deppaths}";
            if [ -e ${step_submod_deppaths} ]; then
                [ -e {{ $app_dir }}/${step_submod_subdirs} ] && rm -rf {{ $app_dir }}/${step_submod_subdirs};
                ln -nfs ${step_submod_deppaths} {{ $app_dir }}/${step_submod_subdirs};
                chgrp -h ${service_owner} {{ $app_dir }}/${step_submod_subdirs};
                if [ -e {{ $release_dir }}/{{ $release }} ]; then
                    [ -e {{ $release_dir }}/{{ $release }}/${step_submod_subdirs} ] && rm -rf {{ $release_dir }}/{{ $release }}/${step_submod_subdirs};
                    ln -nfs ${step_submod_deppaths} {{ $release_dir }}/{{ $release }}/${step_submod_subdirs};
                    chgrp -h ${service_owner} {{ $release_dir }}/{{ $release }}/${step_submod_subdirs};
                fi
            else
                echo "SUBMODULES DEPLOYEDPATH NOT EXISTS : ${step_submod_deppaths}";
            fi
        done
        echo "Submodule Symbolic Link Done.";
    fi
    echo "RemoteVersion Sync Release to App Done.";
@endtask
@task('syncbuildreleasetoapp_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Sync Build Release to App...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    [ ! -d {{ $app_dir }} ] &&  mkdir -p  {{ $app_dir }};
    for subdirname in ${shareddirs};
    do
        if [ -e {{ $app_dir }}/${subdirname} ]; then
            if [ ! -L {{ $app_dir }}/${subdirname} ]; then
                mkdir -p {{ $app_dir }}/${subdirname};
                rm -rf {{ $app_dir }}/${subdirname};
                ln -nfs {{ $shared_dir }}/${subdirname} {{ $app_dir }}/${subdirname};
            fi
        else
            mkdir -p {{ $app_dir }}/${subdirname};
            rm -rf {{ $app_dir }}/${subdirname};
            ln -nfs {{ $shared_dir }}/${subdirname} {{ $app_dir }}/${subdirname};
        fi
        chgrp -f ${service_owner} {{ $app_dir }}/${subdirname};
        chmod -f ug+rwx {{ $app_dir }}/${subdirname};
    done

    if [ {{ intval($deploy_mode=='incr') }} -eq 1 ]; then
        {{-- incr mode--}}
        [ -L {{ $buildreleaseprev_link }}.phar ] && unlink {{ $buildreleaseprev_link }}.phar;
        if [ -e {{ $app_dir }}/{{ $version_name }}.phar ]; then

            {{-- prev appdir exists --}}
            {{--create incr mode prev backup--}}
            cp -RLpf {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;
            if [ -L {{ $app_dir }}/{{ $version_name }}.phar ]; then
                {{--prev appdir is link mode--}}
                mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_link }}.phar;
            fi
        fi
        cp -af {{ $build_dir }}/{{ $release }}.phar {{ $app_dir }}/{{ $version_name }}.phar;

        if [ -e {{ $version_dir }}/buildrelease_name_current ]; then
            lastbuildreleasevalue=`cat {{ $version_dir }}/buildrelease_name_current`;
            ln -nfs {{ $build_dir }}/$lastbuildreleasevalue.phar {{ $buildreleaseprev_link }}.phar;
            cp -af {{ $version_dir }}/buildrelease_name_current {{ $version_dir }}/buildrelease_name_prev;
        fi
        echo "{{ $release }}" > {{ $version_dir }}/buildrelease_name_current;
        [ -e {{ $buildreleaselast_incr }}.phar ] && rm -rf {{ $buildreleaselast_incr }}.phar;
        [ -e {{ $buildreleaselast_link }}.phar ] && unlink {{ $buildreleaselast_link }}.phar;
        [ -f {{ $version_dir }}/buildrelease_name_last ] && unlink {{ $version_dir }}/buildrelease_name_last;
        ln -nfs {{ $build_dir }}/{{ $release }}.phar {{ $buildreleasecurrent_link }}.phar;
    else
        {{-- link mode--}}
        [ -L {{ $buildreleaseprev_link }}.phar ] && unlink {{ $buildreleaseprev_link }}.phar;

        if [ -e {{ $app_dir }}/{{ $version_name }}.phar ]; then
            {{-- prev appdir exists --}}
            if [ -L {{ $app_dir }}/{{ $version_name }}.phar ]; then
                {{--prev appdir is link mode--}}
                {{--create incr mode prev backup--}}
                cp -RLpf {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;

                mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_link }}.phar;
            else
                [ -e {{ $buildreleaseprev_incr }}.phar ] &&  rm -rf {{ $buildreleaseprev_incr }}.phar;
                mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;
            fi
        fi
        ln -nfs {{ $build_dir }}/{{ $release }}.phar {{ $app_dir }}/{{ $version_name }}.phar;
        if [ -e {{ $version_dir }}/buildrelease_name_current ]; then
            lastbuildreleasevalue=`cat {{ $version_dir }}/buildrelease_name_current`;
            ln -nfs {{ $release_dir }}/$lastbuildreleasevalue.phar {{ $buildreleaseprev_link }}.phar;
            cp -af {{ $version_dir }}/buildrelease_name_current {{ $version_dir }}/buildrelease_name_prev;
        fi
        echo "{{ $release }}" > {{ $version_dir }}/buildrelease_name_current;
        [ -e {{ $buildreleaselast_incr }}.phar ] && rm -rf {{ $buildreleaselast_incr }}.phar;
        [ -e {{ $buildreleaselast_link }}.phar ] && unlink {{ $buildreleaselast_link }}.phar;
        [ -f {{ $version_dir }}/buildrelease_name_last ] && unlink {{ $version_dir }}/buildrelease_name_last;
        ln -nfs {{ $build_dir }}/{{ $release }}.phar {{ $buildreleasecurrent_link }}.phar;
    fi
    chgrp -h ${service_owner} {{ $app_dir }}/{{ $version_name }}.phar;
    chmod -Rf ug+rwx {{ $app_dir }}/{{ $version_name }}.phar;
    echo "RemoteVersion Sync Build Release to App Done.";
@endtask

@task('databasemigrate_version',['on' => $server_labels, 'parallel' => true])
    if [ {{ intval($settings['databasemigrate_on_deploy']==1) }} -eq 1 ]; then
        echo "RemoteVersion Release Database Migrate...";
        cd {{ $app_dir }};
        php artisan migrate --env={{ $env }} --force --no-interaction;
        echo "RemoteVersion Release Database Migrate Done.";
    fi
@endtask

@task('cleanupoldreleases_on_remote',['on' => $server_labels, 'parallel' => true])
    echo 'Cleanup up old releases';
    cd {{ $release_dir }};
    {{--ls -1d release_* | head -n -{{ intval($release_keep_count) }} | xargs -d '\n' rm -Rf;--}}
    (ls -rd {{ $release_dir }}/*|head -n {{ intval($release_keep_count+1) }};ls -d {{ $release_dir }}/*)|sort|uniq -u|xargs rm -rf;
    echo "Cleanup up old releases done.";
@endtask
@task('cleanupoldbuildreleases_on_remote',['on' => $server_labels, 'parallel' => true])
    echo 'Cleanup up old build releases';
    cd {{ $build_dir }};
    {{--ls -1d release_* | head -n -{{ intval($release_keep_count) }} | xargs -d '\n' rm -Rf;--}}
    (ls -rd {{ $build_dir }}/*|head -n {{ intval($release_keep_count+1) }};ls -d {{ $build_dir }}/*)|sort|uniq -u|xargs rm -rf;
    echo "Cleanup up old build releases done.";
@endtask

@task('cleanup_tempfiles_local',['on' => 'local'])
    echo 'Cleanup Local tempfiles';
    [ -f {{ $localdeploy_tmp_dir }}/release.tgz ] && rm -rf {{ $localdeploy_tmp_dir }}/release.tgz;
    [ -f {{ $localdeploy_tmp_dir }}/{{ $app_name }}.phar ] && rm -rf {{ $localdeploy_tmp_dir }}/{{ $app_name }}.phar;
    echo "Cleanup Local tempfiles done.";
@endtask
@task('cleanup_tempfiles_remote',['on' => $server_labels, 'parallel' => true])
    echo 'Cleanup Remote tempfiles';
    [ -f {{ $tmp_dir }}/release.tgz ] && rm -rf {{ $tmp_dir }}/release.tgz;
    [ -d {{ $app_base }}/source_prev ] && rm -rf {{ $app_base }}/source_prev;
    [ -f {{ $tmp_dir }}/{{ $app_name }}.phar ] && rm -rf {{ $tmp_dir }}/{{ $app_name }}.phar;
    echo "Cleanup Remote tempfiles done.";
@endtask

@task('rollback_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Release Rollback...";
    if [ {{ intval($settings['databasemigraterollback_on_rollback']==1) }} -eq 1 ]; then
        echo "RemoteVersion Release Database Migrate Rollback...";
        cd {{ $app_dir }};
        php artisan migrate:rollback --env={{ $env }} --force --no-interaction;
        echo "RemoteVersion Release Database Migrate Rollback Done.";
    fi
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    if [ {{ intval($deploy_mode=='incr') }} -eq 1 ]; then
        {{-- incr mode--}}
        if [ -d {{ $releaselast_dir_incr }} ]; then
            if [ -L {{ $app_dir }} ]; then
                {{--prev appdir is link mode--}}
                rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $app_dir }}/ {{ $releaseprev_dir_incr }}/;
                for subdirname in ${shareddirs};
                do
                    if [ -e {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                        if [ ! -L {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                            mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                            rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                            ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                        fi
                    else
                        mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                        rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                        ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                    fi
                    chgrp -f ${service_owner} {{ $releaseprev_dir_incr }}/${subdirname};
                    chmod -f ug+rwx {{ $releaseprev_dir_incr }}/${subdirname};
                done
                unlink {{ $app_dir }};
            else
                {{--prev appdir is incr mode--}}
                [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $releaseprev_dir_incr }};
            fi
            [ -d {{ $releaselast_dir_incr }} ] && mv {{ $releaselast_dir_incr }} {{ $app_dir }};

            [ -d {{ $releasecurrent_dir_link }} ] && mv {{ $releasecurrent_dir_link }} {{ $releaseprev_dir_link }};
            [ -d {{ $releaselast_dir_link }} ] && mv {{ $releaselast_dir_link }} {{ $releasecurrent_dir_link }};

            [ -f {{ $version_dir }}/release_name_prev ] && rm -rf {{ $version_dir }}/release_name_prev;
            [ -f {{ $version_dir }}/release_name_current ] && mv {{ $version_dir }}/release_name_current {{ $version_dir }}/release_name_prev;
            [ -f {{ $version_dir }}/release_name_last ] && mv {{ $version_dir }}/release_name_last {{ $version_dir }}/release_name_current;

            [ -d {{ $releaselast_dir_incr }} ] && rm -rf {{ $releaselast_dir_incr }};
            [ -d {{ $releaselast_dir_link }} ] && unlink {{ $releaselast_dir_link }};
            echo "Reset to last release";
        elif [ -d {{ $releaseprev_dir_incr }} ] && [ ! -d {{ $releaselast_dir_incr }} ]; then
            if [ -L {{ $app_dir }} ]; then
                {{--prev appdir is link mode--}}
                rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $app_dir }}/ {{ $releaselast_dir_incr }}/;
                for subdirname in ${shareddirs};
                do
                    if [ -e {{ $releaselast_dir_incr }}/${subdirname} ]; then
                        if [ ! -L {{ $releaselast_dir_incr }}/${subdirname} ]; then
                            mkdir -p {{ $releaselast_dir_incr }}/${subdirname};
                            rm -rf {{ $releaselast_dir_incr }}/${subdirname};
                            ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaselast_dir_incr }}/${subdirname};
                        fi
                    else
                        mkdir -p {{ $releaselast_dir_incr }}/${subdirname};
                        rm -rf {{ $releaselast_dir_incr }}/${subdirname};
                        ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaselast_dir_incr }}/${subdirname};
                    fi
                    chgrp -f ${service_owner} {{ $releaselast_dir_incr }}/${subdirname};
                    chmod -f ug+rwx {{ $releaselast_dir_incr }}/${subdirname};
                done
                unlink {{ $app_dir }};
            else
                {{--prev appdir is incr mode--}}
                [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $releaselast_dir_incr }};
            fi
            [ -d {{ $releaseprev_dir_incr }} ] && mv {{ $releaseprev_dir_incr }} {{ $app_dir }};

            [ -d {{ $releasecurrent_dir_link }} ] && mv {{ $releasecurrent_dir_link }} {{ $releaselast_dir_link }};
            [ -d {{ $releaseprev_dir_link }} ] && mv {{ $releaseprev_dir_link }} {{ $releasecurrent_dir_link }};

            [ -f {{ $version_dir }}/release_name_last ] && rm -rf {{ $version_dir }}/release_name_last;
            [ -f {{ $version_dir }}/release_name_current ] && mv {{ $version_dir }}/release_name_current {{ $version_dir }}/release_name_last;
            [ -f {{ $version_dir }}/release_name_prev ] && mv {{ $version_dir }}/release_name_prev {{ $version_dir }}/release_name_current;

            [ -d {{ $releaseprev_dir_incr }} ] && rm -rf {{ $releaseprev_dir_incr }};
            [ -d {{ $releaseprev_dir_link }} ] && unlink {{ $releaseprev_dir_link }};
            echo "Rollback to previous release";
        else
            echo "noprevious release to rollback";
        fi
    else
        {{-- link mode--}}
        if [ -d {{ $releaselast_dir_link }} ]; then
            if [ -L {{ $app_dir }} ]; then
                {{--prev appdir is link mode--}}
                rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $app_dir }}/ {{ $releaseprev_dir_incr }}/;
                for subdirname in ${shareddirs};
                do
                    if [ -e {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                        if [ ! -L {{ $releaseprev_dir_incr }}/${subdirname} ]; then
                            mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                            rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                            ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                        fi
                    else
                        mkdir -p {{ $releaseprev_dir_incr }}/${subdirname};
                        rm -rf {{ $releaseprev_dir_incr }}/${subdirname};
                        ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaseprev_dir_incr }}/${subdirname};
                    fi
                    chgrp -f ${service_owner} {{ $releaseprev_dir_incr }}/${subdirname};
                    chmod -f ug+rwx {{ $releaseprev_dir_incr }}/${subdirname};
                done
                [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $releaseprev_dir_link }};
            else
                {{--prev appdir is incr mode--}}
                [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $releaseprev_dir_incr }};
                [ -d {{ $releasecurrent_dir_link }} ] && mv {{ $releasecurrent_dir_link }} {{ $releaseprev_dir_link }};
            fi
            [ -d {{ $releaselast_dir_link }} ] && mv {{ $releaselast_dir_link }} {{ $app_dir }};

            [ -d {{ $releasecurrent_dir_link }} ] && unlink {{ $releasecurrent_dir_link }};
            [ -d {{ $releaselast_dir_link }} ] && cp -af {{ $releaselast_dir_link }} {{ $releasecurrent_dir_link }};

            [ -f {{ $version_dir }}/release_name_prev ] && rm -rf {{ $version_dir }}/release_name_prev;
            [ -f {{ $version_dir }}/release_name_current ] && mv {{ $version_dir }}/release_name_current {{ $version_dir }}/release_name_prev;
            [ -f {{ $version_dir }}/release_name_last ] && mv {{ $version_dir }}/release_name_last {{ $version_dir }}/release_name_current;

            [ -d {{ $releaselast_dir_incr }} ] && rm -rf {{ $releaselast_dir_incr }};
            [ -d {{ $releaselast_dir_link }} ] && unlink {{ $releaselast_dir_link }};
            echo "Reset to last symbolic link";
        elif [ -d {{ $releaseprev_dir_link }} ] && [ ! -d {{ $releaselast_dir_link }} ]; then
            if [ -L {{ $app_dir }} ]; then
                {{--prev appdir is link mode--}}
                rsync --progress -e ssh -avzh --delay-updates --exclude=".git/" {{ $rsyncExcludePatternContext }} --delete --exclude=".git/"  {{ $rsyncExcludePatternContext }} {{ $app_dir }}/ {{ $releaselast_dir_incr }}/;
                for subdirname in ${shareddirs};
                do
                    if [ -e {{ $releaselast_dir_incr }}/${subdirname} ]; then
                        if [ ! -L {{ $releaselast_dir_incr }}/${subdirname} ]; then
                            mkdir -p {{ $releaselast_dir_incr }}/${subdirname};
                            rm -rf {{ $releaselast_dir_incr }}/${subdirname};
                            ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaselast_dir_incr }}/${subdirname};
                        fi
                    else
                        mkdir -p {{ $releaselast_dir_incr }}/${subdirname};
                        rm -rf {{ $releaselast_dir_incr }}/${subdirname};
                        ln -nfs {{ $shared_dir }}/${subdirname} {{ $releaselast_dir_incr }}/${subdirname};
                    fi
                    chgrp -f ${service_owner} {{ $releaselast_dir_incr }}/${subdirname};
                    chmod -f ug+rwx {{ $releaselast_dir_incr }}/${subdirname};
                done
                [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $releaselast_dir_link }};
            else
                {{--prev appdir is incr mode--}}
                [ -d {{ $app_dir }} ] && mv {{ $app_dir }} {{ $releaselast_dir_incr }};
                [ -d {{ $releasecurrent_dir_link }} ] && mv {{ $releasecurrent_dir_link }} {{ $releaselast_dir_link }};
            fi
            [ -d {{ $releaseprev_dir_link }} ] && mv {{ $releaseprev_dir_link }} {{ $app_dir }};

            [ -d {{ $releasecurrent_dir_link }} ] && unlink {{ $releasecurrent_dir_link }};
            [ -d {{ $releaseprev_dir_link }} ] && cp -af {{ $releaseprev_dir_link }} {{ $releasecurrent_dir_link }};

            [ -f {{ $version_dir }}/release_name_last ] && rm -rf {{ $version_dir }}/release_name_last;
            [ -f {{ $version_dir }}/release_name_current ] && mv {{ $version_dir }}/release_name_current {{ $version_dir }}/release_name_last;
            [ -f {{ $version_dir }}/release_name_prev ] && mv {{ $version_dir }}/release_name_prev {{ $version_dir }}/release_name_current;

            [ -d {{ $releaseprev_dir_incr }} ] && rm -rf {{ $releaseprev_dir_incr }};
            [ -d {{ $releaseprev_dir_link }} ] && unlink {{ $releaseprev_dir_link }};
            echo "Rollback to previous symbolic link";
        else
            echo "noprevious link to rollback";
        fi
    fi
    echo "RemoteVersion Release Rollback Done.";
@endtask

@task('buildrollback_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Build Release Rollback...";
    if [ -e {{ $tmp_dir }}/service_owner ]; then
        service_owner=`cat {{ $tmp_dir }}/service_owner`;
    else
        service_owner="{{ $settings['service_owner_default'] }}";
    fi
    shareddirs="{{ implode(' ',$shared_subdirs) }}";
    if [ {{ intval($deploy_mode=='incr') }} -eq 1 ]; then
        {{-- incr mode--}}
        if [ -e {{ $buildreleaselast_incr }}.phar ]; then
            if [ -L {{ $app_dir }}/{{ $version_name }}.phar ]; then
                {{--prev appdir is link mode--}}
                cp -RLpf {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;
                unlink {{ $app_dir }}/{{ $version_name }}.phar;
            else
                {{--prev appdir is incr mode--}}
                [ -e {{ $app_dir }}/{{ $version_name }}.phar ] && mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;
            fi
            [ -e {{ $buildreleaselast_incr }}.phar ] && mv {{ $buildreleaselast_incr }}.phar {{ $app_dir }}/{{ $version_name }}.phar;

            [ -e {{ $buildreleasecurrent_link }}.phar ] && mv {{ $buildreleasecurrent_link }}.phar {{ $buildreleaseprev_link }}.phar;
            [ -e {{ $buildreleaselast_link }}.phar ] && mv {{ $buildreleaselast_link }}.phar {{ $buildreleasecurrent_link }}.phar;

            [ -f {{ $version_dir }}/buildrelease_name_prev ] && rm -rf {{ $version_dir }}/buildrelease_name_prev;
            [ -f {{ $version_dir }}/buildrelease_name_current ] && mv {{ $version_dir }}/buildrelease_name_current {{ $version_dir }}/buildrelease_name_prev;
            [ -f {{ $version_dir }}/buildrelease_name_last ] && mv {{ $version_dir }}/buildrelease_name_last {{ $version_dir }}/buildrelease_name_current;

            [ -e {{ $buildreleaselast_incr }}.phar ] && rm -rf {{ $buildreleaselast_incr }}.phar;
            [ -e {{ $buildreleaselast_link }}.phar ] && unlink {{ $buildreleaselast_link }}.phar;
            echo "Reset to last build release";
        elif [ -e {{ $buildreleaseprev_incr }}.phar ] && [ ! -e {{ $buildreleaselast_incr }}.phar ]; then
            if [ -L {{ $app_dir }}/{{ $version_name }}.phar ]; then
                {{--prev appdir is link mode--}}
                cp -RLpf {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaselast_incr }}.phar;
                unlink {{ $app_dir }}/{{ $version_name }}.phar;
            else
                {{--prev appdir is incr mode--}}
                [ -e {{ $app_dir }}/{{ $version_name }}.phar ] && mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaselast_incr }}.phar;
            fi
            [ -e {{ $buildreleaseprev_incr }}.phar ] && mv {{ $buildreleaseprev_incr }}.phar {{ $app_dir }}/{{ $version_name }}.phar;

            [ -e {{ $buildreleasecurrent_link }}.phar ] && mv {{ $buildreleasecurrent_link }}.phar {{ $buildreleaselast_link }}.phar;
            [ -e {{ $buildreleaseprev_link }}.phar ] && mv {{ $buildreleaseprev_link }}.phar {{ $buildreleasecurrent_link }}.phar;

            [ -f {{ $version_dir }}/buildrelease_name_last ] && rm -rf {{ $version_dir }}/buildrelease_name_last;
            [ -f {{ $version_dir }}/buildrelease_name_current ] && mv {{ $version_dir }}/buildrelease_name_current {{ $version_dir }}/buildrelease_name_last;
            [ -f {{ $version_dir }}/buildrelease_name_prev ] && mv {{ $version_dir }}/buildrelease_name_prev {{ $version_dir }}/buildrelease_name_current;

            [ -e {{ $buildreleaseprev_incr }}.phar ] && rm -rf {{ $buildreleaseprev_incr }}.phar;
            [ -e {{ $buildreleaseprev_link }}.phar ] && unlink {{ $buildreleaseprev_link }}.phar;
            echo "Rollback to previous build release";
        else
            echo "noprevious build release to rollback";
        fi
    else
        {{-- link mode--}}
        if [ -e {{ $buildreleaselast_link }}.phar ]; then
            if [ -L {{ $app_dir }}/{{ $version_name }}.phar ]; then
                {{--prev appdir is link mode--}}
                cp -RLpf {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;
                [ -e {{ $app_dir }}/{{ $version_name }}.phar ] && mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_link }}.phar;
            else
                {{--prev appdir is incr mode--}}
                [ -e {{ $app_dir }}/{{ $version_name }}.phar ] && mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaseprev_incr }}.phar;
                [ -e {{ $buildreleasecurrent_link }}.phar ] && mv {{ $buildreleasecurrent_link }}.phar {{ $buildreleaseprev_link }}.phar;
            fi
            [ -e {{ $buildreleaselast_link }}.phar ] && mv {{ $buildreleaselast_link }}.phar {{ $app_dir }}/{{ $version_name }}.phar;

            [ -e {{ $buildreleasecurrent_link }}.phar ] && unlink {{ $buildreleasecurrent_link }}.phar;
            [ -e {{ $buildreleaselast_link }}.phar ] && cp -af {{ $buildreleaselast_link }}.phar {{ $buildreleasecurrent_link }}.phar;

            [ -f {{ $version_dir }}/buildrelease_name_prev ] && rm -rf {{ $version_dir }}/buildrelease_name_prev;
            [ -f {{ $version_dir }}/buildrelease_name_current ] && mv {{ $version_dir }}/buildrelease_name_current {{ $version_dir }}/buildrelease_name_prev;
            [ -f {{ $version_dir }}/buildrelease_name_last ] && mv {{ $version_dir }}/buildrelease_name_last {{ $version_dir }}/buildrelease_name_current;

            [ -e {{ $buildreleaselast_incr }}.phar ] && rm -rf {{ $buildreleaselast_incr }}.phar;
            [ -e {{ $buildreleaselast_link }}.phar ] && unlink {{ $buildreleaselast_link }}.phar;
            echo "Reset to last build symbolic link";
        elif [ -e {{ $buildreleaseprev_link }}.phar ] && [ ! -e {{ $buildreleaselast_link }}.phar ]; then
            if [ -L {{ $app_dir }}/{{ $version_name }}.phar ]; then
                {{--prev appdir is link mode--}}
                cp -RLpf {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaselast_incr }}.phar;
                
                [ -e {{ $app_dir }}/{{ $version_name }}.phar ] && mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaselast_link }}.phar;
            else
                {{--prev appdir is incr mode--}}
                [ -e {{ $app_dir }}/{{ $version_name }}.phar ] && mv {{ $app_dir }}/{{ $version_name }}.phar {{ $buildreleaselast_incr }}.phar;
                [ -e {{ $buildreleasecurrent_link }}.phar ] && mv {{ $buildreleasecurrent_link }}.phar {{ $buildreleaselast_link }}.phar;
            fi
            [ -e {{ $buildreleaseprev_link }}.phar ] && mv {{ $buildreleaseprev_link }}.phar {{ $app_dir }}/{{ $version_name }}.phar;

            [ -e {{ $buildreleasecurrent_link }}.phar ] && unlink {{ $buildreleasecurrent_link }}.phar;
            [ -e {{ $buildreleaseprev_link }}.phar ] && cp -af {{ $buildreleaseprev_link }}.phar {{ $buildreleasecurrent_link }}.phar;

            [ -f {{ $version_dir }}/buildrelease_name_last ] && rm -rf {{ $version_dir }}/buildrelease_name_last;
            [ -f {{ $version_dir }}/buildrelease_name_current ] && mv {{ $version_dir }}/buildrelease_name_current {{ $version_dir }}/buildrelease_name_last;
            [ -f {{ $version_dir }}/buildrelease_name_prev ] && mv {{ $version_dir }}/buildrelease_name_prev {{ $version_dir }}/buildrelease_name_current;

            [ -e {{ $buildreleaseprev_incr }}.phar ] && rm -rf {{ $buildreleaseprev_incr }}.phar;
            [ -e {{ $buildreleaseprev_link }}.phar ] && unlink {{ $buildreleaseprev_link }}.phar;
            echo "Rollback to previous build symbolic link";
        else
            echo "noprevious build link to rollback";
        fi
    fi
    echo "RemoteVersion Build Release Rollback Done.";
@endtask

@task('databasemigraterollback_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Release Database Migrate Rollback...";
    cd {{ $app_dir }};
    php artisan migrate:rollback --env={{ $env }} --force --no-interaction;
    echo "RemoteVersion Release Database Migrate Rollback Done.";
@endtask

@task('backupapp_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Backup Current Version Release...";
    cd {{ $app_base }}/;
    tar czf {{ $tmp_dir }}/backup_release_files_tmp.tgz {{ $version_name }}/.;
    [ -f {{ $tmp_dir }}/backup_release_files.tgz ] && rm -rf {{ $tmp_dir }}/backup_release_files.tgz;
    mv {{ $tmp_dir }}/backup_release_files_tmp.tgz {{ $tmp_dir }}/backup_release_files.tgz;
    echo "Backup Release File Created At: {{ $tmp_dir }}/backup_release_files.tgz";
    echo "RemoteVersion Backup Current Version Release Done.";
@endtask

@task('backupshareddata_version',['on' => $server_labels, 'parallel' => true])
    echo "RemoteVersion Backup Current Shared Data...";
    cd {{ $app_base }}/;
    tar czf {{ $tmp_dir }}/backup_shareddata_files_tmp.tgz shared;
    [ -f {{ $tmp_dir }}/backup_shareddata_files.tgz ] && rm -rf {{ $tmp_dir }}/backup_shareddata_files.tgz;
    mv {{ $tmp_dir }}/backup_shareddata_files_tmp.tgz {{ $tmp_dir }}/backup_shareddata_files.tgz;
    echo "Backup SharedData File Created At: {{ $tmp_dir }}/backup_shareddata_files.tgz";
    echo "RemoteVersion Backup Current Shared Data Done.";
@endtask
