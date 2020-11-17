class UPMBackend {
  constructor(config) {
    $.ajaxSetup({cache: false, beforeSend: function(xhr){
      if (xhr.overrideMimeType) { xhr.overrideMimeType("application/json"); }
    }});

    this.folderDataLoadFinished = config.folderDataLoad;
    this.serverDataLoadFinished = config.serverDataLoad;
    this.globalDataLoadFinished = config.globalDataLoad;
    this.serverSetOutput = config.serverSetOutput;
    this.distributionConfigFinished = config.distributionConfigFinished;
    this.eolConfigFinished = config.eolConfigFinished;
    this.showPackageChangelogReturn = config.showPackageChangelogReturn;
    this.showPackageInfoReturn = config.showPackageInfoReturn;
    this.inventoryServerListReturn = config.inventoryServerListReturn;
    this.updateServerListReturn = config.updateServerListReturn;
    this.rebootServerListReturn = config.rebootServerListReturn;
    this.rebootDelServerListReturn = config.rebootDelServerListReturn;
    this.usersLoadReturn = config.usersLoadReturn;
    this.rolesLoadReturn = config.rolesLoadReturn;
    
    this.cache = new Cache(-1, false, new Cache.LocalStorageCacheStorage());
    this.cacheTime = 180;

    this.folders = Array();
    this.servers = Array();

    this.users;
    this.roles;
  }

  loadData(refresh) {
    if( refresh ) {
      this.cache.clear();
    }
    this.folders = this.cache.getItem("folders");
    if( this.folders == null ) {
      console.log("folders - no cache, request all");
      this.loadFolders();
    } else {
      console.log("folders - hit cache");
      this.folderDataLoadFinished();
    }

    this.servers = this.cache.getItem("servers");
    if( this.servers == null ) {
      console.log("servers - no cache, request all");
      this.loadServers();
    } else {
      console.log("servers - hit cache");
      this.serverDataLoadFinished();
    }
    this.requestGlobalConfig();
    this.requestDistributionConfig();
    this.requestEOLConfig();
  }
  

  loadFolders() {
    this.backendCall('get_folders', null);
  }

  updateFolders(data) {
    var promises = [];
    this.folders = Array();
    this.folders.length = 0;
    data.forEach( (item) => {
      //console.log( item );
      var folder = new Folder(parseInt(item.folder_child_id), item.child);
      if( parseInt(item.folder_parent_id) > 0 )
        folder.parent_id = parseInt(item.folder_parent_id);
      else
        folder.parent_id = 0;

      this.folders.push( folder );
      var request = this.getFolderInfo( folder.folder_id );
      promises.push( request );
    });
    // console.log( this.folders );
    
    $.when.apply(null, promises).done(() => {
      this.folderDataLoadFinished();
      var cachConfig = {
        expirationAbsolute: null,
	      expirationSliding: this.cacheTime,
	      priority: Cache.Priority.HIGH,
      }
      this.cache.setItem("folders", this.folders, cachConfig);
    });
  }


  getFolderInfo(folder_id) {
    return this.backendCall('get_folder_info', {folder_id: folder_id});
  }

  updateFolderInfo( folder_id, data ) {
    for( var i = 0; i < this.folders.length; i++) {
      if( this.folders[i].folder_id == folder_id ) {
        this.folders[i].name = data.name;
        this.folders[i].icon = data.icon;
        this.folders[i].ssh_private_key = data.ssh_private_key;
        this.folders[i].ssh_public_key = data.ssh_public_key;
        this.folders[i].ssh_port = parseInt(data.ssh_port);
        this.folders[i].ssh_username = data.ssh_username;

        break;
      }
    }
  }

  getFolder(folder_id) {
    for( var i = 0; i < this.folders.length; i++) {
      if( this.folders[i].folder_id == folder_id ) {
        return this.folders[i];
      }
    }
    return null;
  }

  folderGetServerList(folder_id, include_sub_folders) {
    var folder_servers = [];

    for( var i = 0; i < this.servers.length; i++) {
      if( this.servers[i].folder_id == folder_id ) {
        folder_servers.push( this.servers[i] );
      }
    }

    if( include_sub_folders ) {
      var subfolders = this.folderGetSubFolders( folder_id );
      $.each( subfolders, ( key, value ) => {
        var subfolder_id = value.folder_id;
        var subfolder_servers = this.folderGetServerList(subfolder_id, include_sub_folders);
        $.each( subfolder_servers, ( key2, value2 ) => {
          folder_servers.push( value2 );
        });
      });
    }
    return folder_servers;
  }
  
  folderGetSubFolders(folder_id) {
    var subfolders = Array();
    $.each( this.folders, ( key, value ) => {
      if( value.parent_id == folder_id ) {
        subfolders.push( value );
      }
    });
    return subfolders;
  }

  loadServers() {
    this.backendCall('get_servers', null);
  }

  updateServers(data) {
    var promises = [];
    this.servers = Array();
    this.servers.length = 0;
    data.forEach( (item) => {
      //console.log( item );
      var server = new Server(parseInt(item.server_id));
      this.servers.push( server );
      var request = this.getServerInfo( server.server_id );
      promises.push( request );
    });
    $.when.apply(null, promises).done(() => {
      this.serverDataLoadFinished();
    });
  }

  getServerInfo(server_id) {
    return this.backendCall('get_server_info', {server_id: server_id});
  }

  updateServerInfo( server_id, data ) {
    server_id = parseInt(server_id);
    for( var i = 0; i < this.servers.length; i++) {
      if( this.servers[i].server_id == server_id ) {
        this.servers[i].name = data.name;
        this.servers[i].folder_id = parseInt(data.folder_id);
        if( data.folder_id == null )
          this.servers[i].folder_id = 0;
        this.servers[i].hostname = data.hostname;
        this.servers[i].ssh_private_key = data.ssh_private_key;
        this.servers[i].ssh_public_key = data.ssh_public_key;
        this.servers[i].ssh_port = parseInt(data.ssh_port);
        this.servers[i].ssh_username = data.ssh_username;
        this.servers[i].active = data.active;
        this.servers[i].last_inventoried = data.last_inventoried;
        this.servers[i].last_updated = data.last_updated;
        this.servers[i].uptime = data.uptime;
        this.servers[i].updates = data.updates;
        this.servers[i].restart_required = data.restart_required;
        this.servers[i].distribution = data.distribution;
        this.servers[i].distribution_version = data.distribution_version;
        this.servers[i].user_distribution = data.user_distribution;
        this.servers[i].EOL = data.EOL;
        this.servers[i].sheduled_restart = data.sheduled_restart;
        this.servers[i].update_list = data.update_list;
        this.servers[i].imp_updates = data.imp_updates;
        this.servers[i].update_outputs = data.update_outputs;

        var cachConfig = {
          expirationAbsolute: null,
	        expirationSliding: this.cacheTime,
	        priority: Cache.Priority.HIGH,
        }
        this.cache.setItem("servers", this.servers, cachConfig);
        return;
      }
    }
  }
  updateServerSheduledRestart( server_id, sheduled_restart ) {
    for( var i = 0; i < this.servers.length; i++) {
      if( this.servers[i].server_id == server_id ) {
        this.servers[i].sheduled_restart = sheduled_restart;
        break;
      }
    }
    var cachConfig = {
      expirationAbsolute: null,
	    expirationSliding: this.cacheTime,
	    priority: Cache.Priority.HIGH,
    }
    this.cache.setItem("servers", this.servers, cachConfig);
  }

  getServer(server_id) {
    for( var i = 0; i < this.servers.length; i++) {
      if( this.servers[i].server_id == server_id ) {
        return this.servers[i];
      }
    }
    return null;
  }

  addServer( server_name ) {
    return this.backendCall('add_server', {server: server_name});
  }
  addFolder( folder_name ) {
    return this.backendCall('add_folder', {folder: folder_name});
  }
  massImport( import_data ) {
    return this.backendCall('mass_import', {import_data: import_data});
  }
  deleteServer( server_id ) {
    return this.backendCall('delete_server', {server_id: server_id});
  }
  deleteFolder( folder_id ) {
    return this.backendCall('delete_folder', {folder_id: folder_id});
  }
  moveServer( server_id, folder_id ) {
    return this.backendCall('move_server', {server_id: server_id, folder_id: folder_id});
  }
  moveFolder(folder_id, parent_id) {
    return this.backendCall('move_folder', {folder_id: folder_id, parent_id: parent_id});
  }
  inventoryServer( server_id ) {
    return this.backendCall('inventory_server', {server_id: server_id});
  }
  getUpdateOutput( server_update_output_id ) {
    return this.backendCall('server_get_update_output', {server_update_output_id: server_update_output_id} );
  }
  saveGlobalConfig( config ) {
    return this.backendCall('update_global_config', config);
  }
  setSheduleReboot(server_id, timestamp) {
    return this.backendCall('shedule_reboot_add', {server_id: server_id, timestamp: timestamp} );
  }
  deleteSheduleReboot(server_id) {
    return this.backendCall('shedule_reboot_del', {server_id: server_id} );
  }
  saveServerConfig( config ) {
    return this.backendCall('update_server_config', {server_id: config.server_id, name: config.name,
      hostname: config.hostname,
      user_distribution: config.user_distribution,
      ssh_private_key: config.ssh_private_key,
      ssh_port: config.ssh_port,
      ssh_username: config.ssh_username
    });
  }
  saveFolderConfig( config ) {
    return this.backendCall('update_folder_config', {folder_id: config.folder_id, name: config.name, icon: config.icon,
      ssh_private_key: config.ssh_private_key,
      ssh_port: config.ssh_port,
      ssh_username: config.ssh_username
    });
  }
  requestGlobalConfig() {
    return this.backendCall('get_global_config', null);
  }
  requestDistributionConfig() {
    return this.backendCall('get_distribution_config', null);
  }
  requestEOLConfig() {
    return this.backendCall('get_eol_config', null);
  }

  globalConfigReturn(config) {
    this.globalConfig = config;
    this.globalDataLoadFinished();
  }
  getGlobalConfig() {
    return this.globalConfig;
  }
  distributionConfigReturn(config) {
    this.distributionConfig = config;
    this.distributionConfigs = Array();
    this.distributionConfigs.length = 0;
    $.each( config, ( key, value ) => {
      var config = {config_id: value.config_id};
      this.distributionConfigs.push( config );
      this.backendCall('get_distribution_config_1', {config_id: value.config_id});
    });
   
    this.distributionConfigFinished();
  }
  distributionSingleConfigReturn(config) {
    for( var i = 0; i < this.distributionConfigs.length; i++) {
      if( this.distributionConfigs[i].config_id == config.config_id ) {
        this.distributionConfigs[i].distribution_match = config.distribution_match;
        this.distributionConfigs[i].distribution_command = config.distribution_command;
        this.distributionConfigs[i].distribution_version_command = config.distribution_version_command;
        this.distributionConfigs[i].uptime_command = config.uptime_command;
        this.distributionConfigs[i].restart_command = config.restart_command;
        this.distributionConfigs[i].updates_list_command = config.updates_list_command;
        this.distributionConfigs[i].update_info_command = config.update_info_command;
        this.distributionConfigs[i].update_changelog_command = config.update_changelog_command;
        this.distributionConfigs[i].update_system_command = config.update_system_command;
        this.distributionConfigs[i].update_package_command = config.update_package_command;
        this.distributionConfigs[i].reboot_set_command = config.reboot_set_command;
        this.distributionConfigs[i].reboot_get_command = config.reboot_get_command;
        this.distributionConfigs[i].reboot_del_command = config.reboot_del_command;
        break;
      }
    }
  }
  getDistributionSingleConfig(config_id) {
    for( var i = 0; i < this.distributionConfigs.length; i++) {
      if( this.distributionConfigs[i].config_id == config_id ) {
        return this.distributionConfigs[i];
      }
    }
  }
  getDistributionConfig() {
    return this.distributionConfig;
  }
  EOLConfigReturn(config) {
    this.EOLConfig = config;
    this.eolConfigFinished();
  }
  getEOLConfig() {
    return this.EOLConfig;
  }
  getEOLSingleConfig(eol_id) {
    for( var i = 0; i < this.EOLConfig.length; i++) {
      if( this.EOLConfig[i].eol_id == eol_id ) {
        return this.EOLConfig[i];
      }
    }
  }

  updateDistributionConfig(config) {
    return this.backendCall('update_distribution_config', {config_id: config.config_id,
      config_name: config.config_name,
      distri_name: config.distri_name,
      distri_version: config.distri_version,
      uptime: config.uptime,
      restart: config.restart,
      update_list: config.update_list,
      package_info: config.package_info,
      package_changelog: config.package_changelog,
      system_update: config.system_update,
      package_update: config.package_update,
      shedule_reboot_add: config.shedule_reboot_add,
      shedule_reboot_get: config.shedule_reboot_get,
      shedule_reboot_del: config.shedule_reboot_del
    } );
  }
  insertDistributionConfig(config) {
    return this.backendCall('insert_distribution_config', {config_name: config.config_name,
      distri_name: config.distri_name,
      distri_version: config.distri_version,
      uptime: config.uptime,
      restart: config.restart,
      update_list: config.update_list,
      package_info: config.package_info,
      package_changelog: config.package_changelog,
      system_update: config.system_update,
      package_update: config.package_update,
      shedule_reboot_add: config.shedule_reboot_add,
      shedule_reboot_get: config.shedule_reboot_get,
      shedule_reboot_del: config.shedule_reboot_del
    });
  }
  updateEOLConfig( config ) {
    return this.backendCall('update_eol_config', {eol_id: config.eol_id,
      distri_name: config.distri_name,
      eol: config.eol,
    });
  }
  insertEOLConfig( config ) {
    return this.backendCall('insert_eol_config', {distri_name: config.distri_name,
      eol: config.eol,
    });

  }
  deleteDistributionConfig(config_id) {
    return this.backendCall('delete_distribution_config', {config_id: config_id});
  }
  deleteEOLConfig( eol_id ) {
    return this.backendCall('delete_eol_config', {eol_id: eol_id});
  }
  deleteImportantUpdate(server_id, iu_id) {
    return this.backendCall('delete_package_important', {server_id: server_id, iu_id: iu_id});
  }
  requestPackageChangelog(update_id) {
    return this.backendCall('get_package_changelog', {update_id: update_id});
  }
  requestPackageInfo(update_id) {
    return this.backendCall('get_package_info', {update_id: update_id});
  }
  updatePackage(server_id, update_id) {
    return this.backendCall('update_package', {update_id: update_id, server_id: server_id});
  }
  addImportantUpdate(server_id, pack, comment) {
    return this.backendCall('add_package_important', {server_id: server_id, pack: pack, comment: comment});
  }
  editImportantUpdate(server_id, iu_id, pack, comment) {
    return this.backendCall('edit_package_important', {server_id: server_id, iu_id: iu_id, pack: pack, comment: comment});
  }
  deleteImportantUpdate(server_id, iu_id) {
    return this.backendCall('delete_package_important', {server_id: server_id, iu_id: iu_id});
  }
  updateServer(server_id) {
    return this.backendCall('update_server', {server_id: server_id});
  }
  inventoryServerFromServerlist( server_id ) {
    return this.backendCall('inventory_server_from_list', {server_id: server_id});
  }
  updateServerFromServerlist( server_id ) {
    return this.backendCall('update_server_from_list', {server_id: server_id});
  }
  rebootServerFromServerlist( server_id, rebootTimestamp ) {
    return this.backendCall('shedule_reboot_add_list', {server_id: server_id, timestamp: rebootTimestamp} );
  }
  rebootDelServerFromServerlist( server_id ) {
    return this.backendCall('shedule_reboot_del_list', {server_id: server_id} );
  }

  RBACRequestData() {
    this.backendCall('get_roles', null);
    this.backendCall('get_users_with_roles', null);
  }

  backendCall(fct_name, fct_data) {
    return $.ajax({url: "./backend.php",
      method: "POST",
      data: { fct_name: fct_name, fct_data: fct_data },
      success: (data) => {
        if( data.success == false ) {
          if( data.message !== undefined ) {
            $.toast({
              title: data.message,
              type: 'error',
              delay: 5000
            });
            this.serverSetOutput(data.server_id, data.message);
          }
          if( data.server_output !== undefined ) {
            this.serverSetOutput(data.server_id, data.server_output);
          }

          switch( fct_name ) {
            case 'inventory_server_from_list':
              this.inventoryServerListReturn( data.server_id, false, data.message );
              break;
            case 'update_server_from_list':
              this.updateServerListReturn( data.server_id, false, data.message );
              break;
            case 'shedule_reboot_add_list':
              this.rebootServerListReturn(data.server_id, false);
              break;
            case 'shedule_reboot_del_list':
              this.rebootDelServerListReturn(data.server_id, false);
              break;
            case 'update_server':
              break;
          }

          return;
        }
        if( data.message !== undefined ) {
          $.toast({
            title: data.message,
            type: 'success',
            delay: 3000
          });
        }
        switch( fct_name ) {
          case 'update_global_config':
            this.requestGlobalConfig();
            break;
          case 'get_folders':
            this.updateFolders(data.folders);
            break;
          case 'get_servers':
            this.updateServers(data.servers);
            break;
          case 'add_server':
          case 'add_folder':
          case 'delete_server':
          case 'delete_folder':
					case 'move_server':
          case 'move_folder':
          case 'update_folder_config':
          case 'mass_import':
            this.loadData( true );
            break;
          case 'inventory_server':
            this.updateServerInfo(data.server_id, data.server);
            break;
          case 'update_server_config':
            this.updateServerInfo(parseInt(data.server_id), data.server);
            break;
          case 'inventory_server_from_list':
            this.updateServerInfo(data.server_id, data.server);
            this.inventoryServerListReturn( data.server_id, true, "" );
            break;
          case 'update_server_from_list':
            this.updateServerInfo(data.server_id, data.server);
            this.updateServerListReturn( data.server_id, true, data.server_output );
            break;
          case 'get_server_info':
            this.updateServerInfo(parseInt(data.server_id), data.server);
            break;
          case 'get_folder_info':
            this.updateFolderInfo(parseInt(data.folder_id), data.folder);
            break;
          case 'get_global_config':
            this.globalConfigReturn(data.config);
            break;
          case 'get_distribution_config':
            this.distributionConfigReturn(data.config);
            break;
          case 'delete_distribution_config':
          case 'insert_distribution_config':
          case 'update_distribution_config':
            this.requestDistributionConfig();
            break;
          case 'get_distribution_config_1':
            this.distributionSingleConfigReturn(data.config);
            break;
          case 'delete_eol_config':
          case 'insert_eol_config':
          case 'update_eol_config':
            this.requestEOLConfig();
            break;
          case 'get_eol_config':
            this.EOLConfigReturn(data.config);
            break;
          case 'get_package_changelog':
            this.showPackageChangelogReturn(data.changelog);
            break;
          case 'get_package_info':
            this.showPackageInfoReturn(data.info);
            break;
          case 'update_package':
          case 'update_server':
            this.updateServerInfo(parseInt(data.server_id), data.server);
            break;
          case 'add_package_important':
          case 'edit_package_important':
          case 'delete_package_important':
            this.updateServerInfo(parseInt(data.server_id), data.server);
            break;
          case 'shedule_reboot_add':
            this.updateServerSheduledRestart(data.server_id, data.reboot);
            break;
          case 'shedule_reboot_del':
            this.updateServerSheduledRestart(data.server_id, null);
            break;
          case 'shedule_reboot_add_list':
            this.updateServerInfo(data.server_id, data.server);
            this.rebootServerListReturn(data.server_id, true);
            break;
          case 'shedule_reboot_del_list':
            this.updateServerInfo(data.server_id, data.server);
            this.rebootDelServerListReturn(data.server_id, true);
            break;
          case 'server_get_update_output':
            this.serverSetOutput(data.server_id, data.output);
            break;
          case 'get_roles':
            this.roles = data.roles;
            this.rolesLoadReturn();
            break;
          case 'get_users_with_roles':
            this.users = data.users;
            this.usersLoadReturn();
            break;
          case 'add_user_to_role':
            break;
          case 'remove_user_from_role':

        }

        if( data.server_output !== undefined ) {
          this.serverSetOutput(data.server_id, data.server_output);
        } 
      }, 
      error: () => {
        $.toast({
          title: 'Error while executing ' + fct_name,
          type: 'error',
          delay: 5000
        });

        switch( fct_name ) {
          case 'inventory_server_from_list':
            this.inventoryServerListReturn( data.server_id, false, data.message );
            break;
          case 'inventory_server':
            break;
          case 'update_server_from_list':
            this.updateServerListReturn( data.server_id, false, data.message );
            break;
          case 'update_server':
            break;
          case 'shedule_reboot_add_list':
            this.rebootServerListReturn(data.server_id, false);
            break;
          case 'shedule_reboot_del_list':
            this.rebootDelServerListReturn(data.server_id, false);
            break;

        }

      }
    });
  }
}

