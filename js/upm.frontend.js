class UPMFrontend {
  constructor(init_finished_callback) {
    console.log("upm frontend version 0.1.2");
    this.init_finished_callback = init_finished_callback;

    var _folderDataLoad = this.folderDataLoad.bind(this);
    var _serverDataLoad = this.serverDataLoad.bind(this);
    var _globalDataLoad = this.globalDataLoad.bind(this);
    var _serverSetOutput = this.serverSetOutput.bind(this);
    var _distributionConfigFinished = this.distributionConfigFinished.bind(this);
    var _eolConfigFinished = this.eolConfigFinished.bind(this);
    var _showPackageChangelogReturn = this.showPackageChangelogReturn.bind(this);
    var _showPackageInfoReturn = this.showPackageInfoReturn.bind(this);
    var _inventoryServerListReturn = this.inventoryServerListReturn.bind(this);
    var _updateServerListReturn = this.updateServerListReturn.bind(this);
    var _rebootServerListReturn = this.rebootServerListReturn.bind(this);
    var _rebootDelServerListReturn = this.rebootDelServerListReturn.bind(this);
    var _usersLoadReturn = this.usersLoadReturn.bind(this);
    var _rolesLoadReturn = this.rolesLoadReturn.bind(this);

    var backend_config = {
      folderDataLoad: _folderDataLoad,
      serverDataLoad: _serverDataLoad,
      globalDataLoad: _globalDataLoad,
      serverSetOutput: _serverSetOutput,
      distributionConfigFinished: _distributionConfigFinished,
      eolConfigFinished: _eolConfigFinished,
      showPackageChangelogReturn: _showPackageChangelogReturn,
      showPackageInfoReturn: _showPackageInfoReturn,
      inventoryServerListReturn: _inventoryServerListReturn,
      updateServerListReturn: _updateServerListReturn,
      rebootServerListReturn: _rebootServerListReturn,
      rebootDelServerListReturn: _rebootDelServerListReturn,
      usersLoadReturn: _usersLoadReturn,
      rolesLoadReturn: _rolesLoadReturn,
    }
    this.add = false;

    this.viewServer = 1;
    this.viewFolder = 2;
    this.currentView = undefined;
    this.server_data_finished = false;
    this.folder_data_finished = false;

    this.users_data_finished = false;
    this.roles_data_finished = false;

    this.batchrunCount;
    this.batchrunFinished;
    this.batchrunError;
    this.backend = new UPMBackend(backend_config);
  }
  timerStart() { sT = new Date(); }
  timerEnd(text) { eT = new Date(); var tD = eT - sT; console.log( text + " " + tD + "ms"); }

  loadData() {
    this.backend.loadData();
  }

  loadRBAC() {
    this.backend.RBACRequestData();
  }

  usersLoadReturn() {
    this.users_data_finished = true;
    if( this.roles_data_finished )
      this.rbacSetData();
  }

  rolesLoadReturn() {
    this.roles_data_finished = true;
    if( this.users_data_finished )
      this.rbacSetData();
  }


  rbacSetData() {
    var users = this.backend.users;
    var roles = this.backend.roles;

    this.updateRBACData(users, roles);
    this.updateRBACDescription(roles);
  }

  updateRBACData(users, roles) {
    $('#rbac-table > thead').empty();
    $('#rbac-table > tbody').empty();

    var head = '<tr>';
    head += '<th scope="col">Username \\ Role</th>';

    roles.forEach( (role, index) => {
      head += '<th scope="col">' + role.Title + '</th>';
    });
    head += '</tr>';
    $('#rbac-table > thead').append( head );


    users.forEach( (user, index) => {
      var row = '<tr>';
      row += '<th scope="row">' + user.name + '</th>';

      for(var i = 0; i < roles.length; i++) {
        var role = roles[i].Title;
        var checked = "";
        user.roles.forEach( (r, index) => {
          if( role == r.Title )
            checked = "checked";
        });

        var ci = '_ci' + index + '_' + i;
        var checkbox =  '<div class="custom-control custom-checkbox">';
        checkbox += '<input type="checkbox" class="custom-control-input server-checkbox" id="customCheck' + ci + '" ' + checked + ' data-username="' + user.name + '" data-role="' + role + '">';
        checkbox += '<label class="custom-control-label" for="customCheck' + ci + '"></label>'
        checkbox += '</div>'

        row += '<td>' + checkbox + '</td>';
      }

      row += '</tr>';
      $('#rbac-table > tbody').append( row );
    });
  }

  updateRBACDescription(roles) {
    $('#rbac-descriptions').empty();

    roles.forEach( (role, index) => {
      var card = '<div class="card mt-2">';
      card += '<div class="card-body">';
      card += '<h5 class="card-title">' + role.Title + '</h5>';
      card += '<p class="card-text">' + role.Description + '</p>';
      card += '</div></div>';

      $('#rbac-descriptions').append( card );
    });
  }

  folderDataLoad() {
    this.folder_data_finished = true;
    if( this.folder_data_finished && this.server_data_finished ) {
      this.updateFolderList();
      this.init_finished_callback();
    }
  }
  serverDataLoad() {
    this.server_data_finished = true;
    if( this.folder_data_finished && this.server_data_finished ) {
      this.updateFolderList();
      this.init_finished_callback();
    }
  }
  globalDataLoad() {
    var config = this.backend.getGlobalConfig();
    console.log("UPM DB version " + config.version);
  }
  serverSetOutput(server_id, output) {
    if( this.currentView == this.viewServer ) {
      if( $('#server-info-name').attr( 'data-serverid') == server_id ) {
        $('#server-output').val( output );
        var textarea = $('#server-output');
        if(textarea.length)
          textarea.scrollTop(textarea[0].scrollHeight - textarea.height());
      }
    }
  }

  updateFolderList() {
    $('#folderlist').empty();
    $('#select-move-server').empty();
    $('#select-move-folder').empty();

    this.folderListAddFolder( 0, 'Root', '');
    var option = '<option value="0" data-folderid="0">Root</option>';
    $('#select-move-server').append( option );
    $('#select-move-folder').append( option );

    $.each( this.backend.folders, function( key, value ) {
      var option = '<option value="' + value.folder_id + '" data-folderid="' + value.folder_id + '">' + value.name + '</option>';
      $('#select-move-server').append( option );
      $('#select-move-folder').append( option );

    });

    this.updateFolderListRecursive(0);
  }

  updateFolderListRecursive(folder_id_current) {
    $.each( this.backend.folders, ( key, value ) => {
      if( value.parent_id == folder_id_current || (folder_id_current == 0 && value.parent_id == undefined)) {
        this.folderListAddSubFolder(value.folder_id, value.name, value.icon, folder_id_current);
        this.updateFolderListRecursive(value.folder_id);
      }
    });
  }

  folderListAddFolder( folder_id, folder_name, icon ) {
    var s = this.backend.folderGetServerList( folder_id, false );
    var li = '<div class="list-group list-group-root well">';
    li = li + '<a href="?folder_id=0" class="list-group-item list-group-item-action folder-item" data-folderid="' + folder_id + '">';
    li = li + '<strong>' + folder_name + '</strong>';
    li = li + '<span class="badge badge-secondary float-right folderlist-count" data-folderid="' + folder_id + '">' + s.length + '</span>';
    li = li + '</a>';
    li = li + '<div class="list-group folder-itemadd pr-0" data-folderidadd="' + folder_id + '"></div>';
    li = li + '</div>';
    $('#folderlist').append(li);
  }

  folderListAddSubFolder( folder_id, folder_name, icon, parent_id ) {
    var s = this.backend.folderGetServerList( folder_id, false );
    var li = '<a href="?folder_id=' + folder_id + '" class="list-group-item list-group-item-action folder-item" data-folderid="' + folder_id + '">';
    li = li + '<span><i class="' + icon + '"></i>';
    li = li + '<strong class="pl-2">' + folder_name + '</strong>';
    li = li + '<span class="badge badge-secondary float-right folderlist-count" data-folderid="' + folder_id + '">' + s.length + '</span>';
    li = li + '</a>';
    li = li + '<div class="list-group folder-itemadd pr-0" data-folderidadd="' + folder_id + '"></div>';
    var el = $('#folderlist').find("[data-folderidadd='" + parent_id + "']");
    el.append(li);
  }

  folderListSelectFolder( folder_id ) {
    $( '#server-info' ).hide();
    $( '#folder-info' ).show();

    $('a.folder-item').each( function(key, value) {
      $(value).removeClass( "active" );
      if( $(value).attr("data-folderid") == folder_id )
        $(value).addClass( "active" );
    });

    $('span.folderlist-count').each( function(key, value) {
      $(value).removeClass( "badge-light" );
      if( $(value).attr("data-folderid") == folder_id )
        $(value).addClass( "badge-light" );
    });

    this.showSingleFolder( folder_id );
  }

  showSingleFolder( folder_id ) {
    $('#server-info').hide();
    $('#folder-info').show();

    this.currentView = this.viewFolder;
    $('#folder-info-name').attr( 'data-folderid', folder_id );
    $('#link-folder').attr('href', 'index.html?folder_id=' + folder_id );
    $('#folder-info-icon').removeClass();
    var folder = null;
    if( folder_id == 0 ) {
      $('#button-delete-folder').hide();
      $('#button-edit-folder').hide();

      $('#folder-info-name').text( 'Root' );
    } else {
      $('#button-delete-folder').show();
      $('#button-edit-folder').show();

      folder = this.backend.getFolder( folder_id );
      if( folder == null )
        return;
      $('#folder-info-name').text( folder.name );
      $('#folder-info-icon').removeClass();
      $('#folder-info-icon').addClass(folder.icon);

      $('#select-move-folder option').removeAttr('selected');
      $('#select-move-folder option').removeAttr('disabled');
      $('#select-move-folder option[value=' + folder_id  + ']').attr('disabled', 'disabled');
    }

    var include_sub_folders = $('#switch-include-subfolders').is(':checked');

    var folder_servers = this.backend.folderGetServerList( folder_id, include_sub_folders );

    var server_count = 0;
    var server_with_updates = 0;
    var server_with_bad_eol = 0;
    var server_with_restart = 0;
    var server_with_sheduled_reboot = 0;

    $('#folder-server-list > tbody').empty();
    $.each( folder_servers, ( key, value ) => {
      server_count++;

      if( value.updates > 0 )
        server_with_updates++;

      if( value.EOL != null ) {
        if( !this.checkEOL( value.EOL ) ) {
          server_with_bad_eol++;
        }
      } else {
        server_with_bad_eol++;
      }

      if( value.restart_required == 1 )
        server_with_restart++;

      if( value.sheduled_restart != null )
        server_with_sheduled_reboot++;
      this.folderServerListSetServer( value );
      return;
    });

    $('#folder-info-server-count').text( server_count );
    $('#folder-info-server-w-updates').text( server_with_updates );
    $('#folder-info-server-bad-eol').text( server_with_bad_eol );
    $('#folder-info-restartrequired').text( server_with_restart );
    $('#folder-info-sheduled-reboot').text( server_with_sheduled_reboot );

    this.setServerListColumns();
  }

  folderServerListSetServer(server) {
    var row = this.folderServerListGetServerRow( server.server_id );

    var colSel = $(row).find(".sever-select");
    var colName = $(row).find(".server-name");
    var colUpd = $(row).find(".updates");
    var colUpdL = $(row).find(".update_list");
    var colSys = $(row).find(".sys");
    var colEOL = $(row).find(".eol");
    var colRR = $(row).find(".rr");
    var colSR = $(row).find(".sr");
    var colInv = $(row).find(".inventory");
    var colPat = $(row).find(".update");
    var colOut = $(row).find(".output");
    var colOutF = $(row).find(".output-full");

    $(colName).html('<a href="?server_id=' + server.server_id + '">' + server.name + '</a>');

    var distri = server.distribution;
    var distri_text = server.distribution + " " + server.distribution_version;
    if( server.user_distribution != null && server.user_distribution != "" ) {
      distri = server.user_distribution;
      distri_text = server.user_distribution;
    }

    var distri_icon = "";
    // add new matches -> https://github.com/lukas-w/font-logos
    switch( distri ) {
      case 'RedHat':
      case 'OracleServer':
        distri_icon = '<span class="fl-redhat"></span>';
        break;
      case 'CentOS':
        distri_icon = '<span class="fl-centos"></span>';
        break;
      case 'Ubuntu':
        distri_icon = '<span class="fl-ubuntu"></span>';
        break;
      case 'Debian':
        distri_icon = '<span class="fl-debian"></span>';
        break;
      default:
        distri_icon = '<span class="fl-tux"></span>';
        break;
    }
    $(colSys).html(distri_icon);
    $(colSys).attr('data-toggle', "tooltip");
    $(colSys).attr('data-placement', "top");
    $(colSys).attr('title', distri_text);
    
    var updates = "?";
    var update_list = "";
    var update_list2 = "";
    var has_important_updates = false;
    if( server.update_list != undefined ) {
      if( server.update_list.length > 0 ) {
        update_list = '<ul class="text-left pl-4">';
        update_list2 = '<ul class="text-left pl-4">';

        $.each( server.update_list, function( key2, value2) {
          var comment = value2.comment;
          var is_important = false;
          if( value2.important_update_id != null )
            is_important = true;
          if( is_important ) {
            has_important_updates = true;
            comment = comment.replace(/\n/g, "<br />");
            update_list += '<li class="text-danger" data-html="true" data-toggle="tooltip" data-placement="top" title="' + comment + '">' + value2.package + '</li>';
            update_list2 += "<li class='text-danger'>" + value2.package + '</li>';
          } else {
            update_list += '<li>' + value2.package + '</li>';
            update_list2 += '<li>' + value2.package + '</li>';
          }
        });
        $('[data-toggle="tooltip"]').tooltip();
        update_list += '</ul>';
        update_list2 += '</ul>';
      }
    }

    if( server.updates != null )
      updates = server.updates;

    $(colUpd).attr('data-html', 'true');
    $(colUpd).attr('data-toggle', 'tooltip');
    $(colUpd).attr('data-placement', 'top');
    $(colUpd).attr('data-original-title', update_list2);
    
    if( has_important_updates ) {
      $(colName).addClass('table-danger');
      $(colUpd).addClass('table-danger');
      $(row).find(".server-checkbox").find('span').removeClass('checked');
      $(row).find(".server-checkbox").prop('checked', false);
    } else {
      $(colName).removeClass('table-danger');
      $(colUpd).removeClass('table-danger');
    }

    $(colUpdL).html(update_list);

    if( server.updates == null )
      $(colUpd).text("?");
    else
      $(colUpd).text(server.updates);
    

    $(colEOL).removeClass('table-success table-danger');
    var eol = "?";
    if( server.EOL != null ) {
      if( this.checkEOL( server.EOL ) ) {
        $(colEOL).addClass('table-success');
      } else {
        $(colEOL).addClass('table-danger');
      }
      eol = this.getEOLString(server.EOL);
    }
    $(colEOL).text(eol);

    $(colRR).removeClass('table-success table-danger');
    var rr = "?";
    if( server.restart_required != null ) {
      if( server.restart_required == 0 ) {
        rr = "No";
        $(colRR).addClass('table-success');
      } else if( server.restart_required == 1 ) {
        rr = "Yes";
        $(colRR).addClass('table-danger');
      } else if( server.restart_required == 2 ) {
        rr = "-";
        $(colRR).addClass('table-secondary');
      } else {
        rr = "?";
        $(colRR).addClass('table-secondary');
      }
    }
    $(colRR).text(rr);

    if( server.sheduled_restart != null ) {
      $(colSR).html( '<span class="badge badge-danger">' + server.sheduled_restart + '</span>');
    } else {
      $(colSR).text( '-' );
    }
    
    $('[data-toggle="tooltip"]').tooltip();

  }


  folderServerListGetServerRow( server_id ) {
    var row = $('#folder-server-list > tbody > tr > td[data-server_id="' + server_id + '"]').parent();

    if( row.length > 0 )
      return row;

    var index = $('#folder-server-list tr').length;
    var checkbox =  '<div class="custom-control custom-checkbox">';
    checkbox += '<input type="checkbox" class="custom-control-input server-checkbox" id="customCheck' + index + '" checked>';
    checkbox += '<label class="custom-control-label" for="customCheck' + index + '"></label>'
    checkbox += '</div>'

    var newrow = '<tr><td class="index" data-server_id="' + server_id + '">' + index + "</td>";
    newrow += '<td class="server-select">' + checkbox + '</td>';
    newrow += '<td class="server-name"></td>';
    newrow += '<td class="updates"></td>';
    newrow += '<td class="update_list"></td>';
    newrow += '<td class="sys py-0 my-0" style="font-size: 21px;"></td>';
    newrow += '<td class="eol"></td>';
    newrow += '<td class="rr"></td>';
    newrow += '<td class="sr"></td>';
    newrow += '<td class="inventory"></td>';
    newrow += '<td class="update"></td>';
    newrow += '<td class="output"></td>';
    newrow += '<td class="output-full"></td>';

    $('#folder-server-list > tbody').append( newrow );
    row = $('#folder-server-list > tbody > tr > td[data-server_id="' + server_id + '"]').parent();

    return row;
  }

  showSingleServer( server_id ) {
    $( '#folder-info' ).hide();
    $( '#server-info' ).show();

    this.currentView = this.viewServer;
    // different server -> clear server output
    // ToDo save server output for any server
    if( $('#server-info-name').attr( 'data-serverid') != server_id ) {
      $('#server-output').val('');
    }
    var server = this.backend.getServer( server_id );
    if( server == null )
      return false;

    $('#server-info-name').text( server.name );
    $('#server-info-name').attr( 'data-serverid', server.server_id );

    $('#select-move-server option').removeAttr('selected');
    $('#select-move-server option[value=' + server.folder_id  + ']').attr('selected','selected');

    $('#link-server').attr('href', 'index.html?server_id=' + server_id );

    if( server.hostname != null )
      $('#server-info-hostname').text(server.hostname);

    var distri = "";
    if( server.user_distribution != null && server.user_distribution != "") {
      if( server.distribution != null && server.distribution_version != null )
        distri = server.user_distribution + " (" + server.distribution + " " + server.distribution_version + ")";
      else if( server.distribution != null )
        distri = server.user_distribution + " (" + server.distribution + ")";
      else
        distri = server.user_distribution;
    } else {
      if( server.distribution != null && server.distribution_version != null )
        distri = server.distribution + " " + server.distribution_version;
      else if ( server.distribution != null )
        distri = server.distribution;
      else
        distri = 'Unknown';

    }

    if( server.EOL == null ) {
        distri += ' <span class="badge badge-secondary">EOL - ?</span>';
    } else {
      var eol_str = this.getEOLString( server.EOL );
      if( this.checkEOL( server.EOL ) ) {
        distri += ' <span class="badge badge-success">EOL - ' + eol_str + '</span>';
      } else {
        distri += ' <span class="badge badge-danger">EOL - ' + eol_str + '</span>';
      }
    }

    $('#server-info-distribution').html( distri );

    $('#server-last-inventoried').html( server.last_inventoried );
    $('#server-last-updated').html( server.last_updated );

    if( server.uptime != null && server.uptime != '' ) {
      var uptime = this.uptimeString(server.uptime);
      $('#server-info-uptime').text( uptime  );
    } else {
      $('#server-info-uptime').text('Unknown');
    }

    $('#server-info-restartrequired').removeClass('text-danger');
    if( server.restart_required != null ) {
      if( server.restart_required == 1 ) {
        $('#server-info-restartrequired').addClass('text-danger');
        $('#server-info-restartrequired').text( 'Yes!' );
      } else if ( server.restart_required == 0 ) {
        $('#server-info-restartrequired').text( 'no' );
      } else if ( server.restart_required == 2 ) {
        $('#server-info-restartrequired').text( '-' );
      } else {
        $('#server-info-restartrequired').text( '?' );
      }

    } else {
      $('#server-info-restartrequired').text( 'Unknown' );
    }

    if( server.sheduled_restart != null ) {
      $('#button-server-reboot-add').prop('disabled', true);
      $('#button-server-reboot-del').prop('disabled', false);
      $('#server-info-sheduled-restart').text(server.sheduled_restart);
      $('#server-info-sheduled-restart').addClass('text-danger');
    } else {
      $('#button-server-reboot-del').prop('disabled', true);
      $('#button-server-reboot-add').prop('disabled', false);
      $('#server-info-sheduled-restart').text('-');
      $('#server-info-sheduled-restart').removeClass('text-danger');
    }

    if( server.updates != null )
      $('#server-info-updates').text( server.updates );
    else
      $('#server-info-updates').text( 'Unknown' );

    $('#select-server-update-output').empty();
    var option = '<option value="-1">-</option>';
    $('#select-server-update-output').append( option );
    $.each( server.update_outputs, function( key, value ) {
      var option = '<option value="' + value.server_update_output_id + '">' + value.update_date + '</option>';
      $('#select-server-update-output').append( option );
    });


    // Table server-updates
    $('#server-updates > tbody').empty();
    var updates = server.update_list;
    if( updates === undefined )
      return true;

    var index = 0;
    if( updates.length == 0 ) {
      $('#server-updates-container').hide();
      $('#button-update-server').prop('disabled', true);
    } else {
      $('#server-updates-container').show();
      $('#button-update-server').prop('disabled', false);
      $.each( updates, function( key, value ) {
        index++;

        var id = value.update_id;
        var iu_id = value.important_update_id;
        var pkg = value.package;
        var comment = value.comment;
        var is_important = false;
        if( value.important_update_id != null )
          is_important = true;

        var row = '<tr><td data-update_id="' + id + '">' + index + "</td>";

        if( is_important ) {
          comment = comment.replace(/\n/g, "<br />");
          row += '<td class="package-name table-danger" data-html="true" data-toggle="tooltip" data-placement="top" title="' + comment + '">' + pkg + '</td>';
        } else {
          row += '<td class="package-name">' + pkg + '</td>';
        }

        row += '<td><button id="btn-changelog-package-' + index + '" data-update_id="' + id + '" type="button" class="btn btn-sm btn-info btn-changelog-package mr-2">Changelog</button>';
        row += '<button id="btn-info-package-' + index + '" data-update_id="' + id + '" type="button" class="btn btn-sm btn-info btn-info-package mr-2">Info</button>';
        row += '<button id="btn-update-package-' + index + '" data-server_id="' + server_id + '" data-update_id="' + id + '" type="button" class="btn btn-sm btn-secondary btn-update-package mr-2">Update</button>';
        if( is_important ) {
          row += '<button id="btn-update-unmark-iu-' + index + '" data-server_id="' + server_id + '" data-iu_id="' + iu_id + '" data-update_id="' + id + '" type="button" class="btn btn-sm btn-secondary btn-update-unmark-iu">Unmark important</button>';
        } else {
          row += '<button id="btn-update-mark-iu-' + index + '" data-server_id="' + server_id + '" data-update_id="' + id + '" type="button" class="btn btn-sm btn-secondary btn-update-mark-iu">Mark as important</button>';
        }

        row += '</td>';
        row += '</tr>';
        $('#server-updates > tbody').append( row );
      });
      $('[data-toggle="tooltip"]').tooltip();
    }
    
    // Table important updates
    $('#important-updates > tbody').empty();

    $.each( server.imp_updates, function( key, value) {
      var id = value.important_update_id;
      var pack = value.package;
      var comment = value.comment;

      var row = '<tr><td class="package-name" data-iu_id="' + id + '">' + pack + "</td>";
      row += '<td class="comment">' + comment + '</td>';

      row += '<td><button data-server_id="' + server_id + '" data-iu_id="' + id + '" type="button" class="btn btn-sm btn-info btn-iu-edit mr-2">Edit</button>';
      row += '<button data-server_id="' + server_id + '" data-iu_id="' + id + '" type="button" class="btn btn-sm btn-danger btn-iu-delete mr-2">Delete</button>';

      row += '</td>';
      row += '</tr>';
      $('#important-updates > tbody').append( row );
    });
    $("#button-add-iu").attr("data-server_id", server_id);

    return true;
  }


  uptimeString( uptime ) {
    var now = Date.now();
    var diff = now - uptime*1000;

    var Years = Math.floor(diff/1000/60/60/24/365);
    diff -= Years*1000*60*60*24*365;

    var Months = Math.floor(diff/1000/60/60/24/30);
    diff -= Months*1000*60*60*24*30;

    var Days = Math.floor(diff/1000/60/60/24);
    diff -= Days*1000*60*60*24;

    var UptimeStr = ""
    if( Years > 0 )
      UptimeStr = Years + " years ";
    if( Months > 0 )
      UptimeStr = UptimeStr + Months + " months ";

    UptimeStr = UptimeStr + Days + " days ";

    var ds = new Date( uptime*1000 );
    var year = ds.getFullYear();
    var month = ds.getMonth()+1;
    var day = ds.getDate();
    var hour = ds.getHours();
    var min = ds.getMinutes();

    var UptimeStr = UptimeStr + "(" + ('00'+day).slice(-2) + "." + ('00'+month).slice(-2) + "." + year + " " + ('00'+hour).slice(-2) + ":" + ('00'+min).slice(-2)+ ")";

    return UptimeStr;
  }

  checkEOL( EOL ) {
    var now = Date.now();
    var EOLDate = Date.parse(EOL);
    if( EOLDate < now )
      return false;
    return true;
  }
  getEOLString( EOL ) {
    var EOLDate = new Date(EOL);

    var year = EOLDate.getFullYear();
    var month = EOLDate.getMonth()+1;
    var day = EOLDate.getDate();
    var EOLSTR = ('00'+day).slice(-2) + "." + ('00'+month).slice(-2) + "." + year;
    return EOLSTR;
  }

  getDateTimeLocalString(d) {
    var year = d.getFullYear();

    var month = (d.getMonth() + 1).toString().length === 1 ? '0' + (d.getMonth() + 1).toString() : d.getMonth() + 1;
    var date = d.getDate().toString().length === 1 ? '0' + (d.getDate()).toString() : d.getDate();
    var hours = d.getHours().toString().length === 1 ? '0' + d.getHours().toString() : d.getHours();
    var minutes = d.getMinutes().toString().length === 1 ? '0' + d.getMinutes().toString() : d.getMinutes();
    var seconds = d.getSeconds().toString().length === 1 ? '0' + d.getSeconds().toString() : d.getSeconds();

    var formattedDateTime = year + '-' + month + '-' + date + 'T' + hours + ':' + minutes + ':' + seconds;

    return formattedDateTime;
  }

  addServer( server_name ) {
    this.backend.addServer( server_name );
  }
  addFolder( folder_name ) {
    this.backend.addFolder( folder_name );
  }
  massImport( import_data ) {
    this.backend.massImport( import_data );
  }
  deleteServer( server_id ) {
    this.backend.deleteServer( server_id );
  }
  deleteFolder( folder_id ) {
    this.backend.deleteFolder( folder_id );
  }
  moveServer( server_id, folder_id ) {
    this.disableElement("#button-move-server");
    this.elementAddSpinner("#button-move-server", 'text-info');
    var request = this.backend.moveServer( server_id, folder_id );
    $.when(request).done(() => {
      this.enableElement("#button-move-server");
      this.elementRemoveSpinner("#button-move-server");
      this.showSingleServer( server_id );
    });
  }
  moveFolder(folder_id, parent_id) {
    this.disableElement("#button-moveFolder");
    this.elementAddSpinner("#button-moveFolder", 'text-info');
    var request = this.backend.moveFolder(folder_id, parent_id);
    $.when(request).done(() => {
      this.enableElement("#button-moveFolder");
      this.elementRemoveSpinner("#button-moveFolder");
      this.showSingleFolder( folder_id );
    });
  }
  inventoryServer( server_id ) {
    this.timerStart();
    this.disableElement("#button-inventory-server");
    this.elementAddSpinner("#button-inventory-server", 'text-info');
    var request = this.backend.inventoryServer( server_id );
    $.when(request).done(() => {
      this.enableElement("#button-inventory-server");
      this.elementRemoveSpinner("#button-inventory-server");
      this.showSingleServer( server_id );
      this.timerEnd("inventury host finished in");
    });
  }

  disableElement( el ) {
    $(el).prop("disabled", true);
  }
  enableElement( el ) {
    $(el).prop("disabled", false);
  }
  elementAddSpinner( el, color ) {
    var spinner  = '<span class="spinner-border spinner-border-sm ml-2 ' + color + ' " role="status"></span>';
    $(el).append( spinner );
  }

  elementRemoveSpinner( el ) {
    $(el + " .spinner-border").remove();
  }

  getUpdateOutput( server_update_output_id ) {
    this.backend.getUpdateOutput( server_update_output_id );
  }

  showAddDistributionConfig() {
    $('#distri-configname').attr('data-configid', -1);
    $('#distri-configname').val('');
    $('#distri-distriname').val('');
    $('#distri-version').val('');
    $('#distri-uptime').val('');
    $('#distri-restart').val('');
    $('#distri-update-list').val('');
    $('#distri-package-info').val('');
    $('#distri-package-changelog').val('');
    $('#distri-update-system').val('');
    $('#distri-update-package').val('');
    $('#distri-shedule-reboot-add').val('');
    $('#distri-shedule-reboot-get').val('');
    $('#distri-shedule-reboot-del').val('');

    $('#modal-distribution-config').modal('show');
  }
  showEOLConfig() {
    $('#eol-distri-configname').attr('data-eolid', -1);
    $('#eol-distri-configname').val('');
    $('#distri-eol').val('');

    $('#modal-eol-config').modal('show');
  }
  saveGlobalConfig() {
    var default_ssh_private_key = $('#global-ssh-private-key').val();
    var default_ssh_port = $('#global-ssh-port').val();
    var default_ssh_username = $('#global-ssh-username').val();
    var default_distribution_command = $('#global-distribution-command').val();
    var default_distribution_version_command = $('#global-distribution-version-command').val();

    this.backend.saveGlobalConfig( {default_ssh_private_key: default_ssh_private_key,
      default_ssh_port: default_ssh_port,
      default_ssh_username: default_ssh_username,
      default_distribution_command: default_distribution_command,
      default_distribution_version_command: default_distribution_version_command,
    });

    $('#modal-global-settings').modal('hide');
  }
  showServerEdit(server_id) {
    var server = this.backend.getServer( server_id );
    if( server == null )
      return false;
    $('#server-config-name').val(server.name);
    $('#server-config-hostname').val(server.hostname);
    $('#server-config-distri').val(server.user_distribution);
    $('#server-config-privatekey').val(server.ssh_private_key);
    if( server.ssh_port == 0 )
      $('#server-config-sshport').val('');
    else
      $('#server-config-sshport').val(server.ssh_port);
    $('#server-config-sshusername').val(server.ssh_username);

    $('#modal-server-config').modal('show');
  }

  showImpUpdates() {
    $('#modal-important-updates').modal('show');
  }

  showSheduleReboot() {
    var server_id = $( '#server-info-name' ).attr("data-serverid");
    $('#datetime-shedule-reboot').attr('data-serverid', server_id);
    $('#datetime-shedule-reboot').attr('data-folderlist', '0');
    $('#modal-shedule-reboot').modal('show');
  }
  showSheduleRebootServerList() {
    $('#datetime-shedule-reboot').attr('data-serverid', -1);
    $('#datetime-shedule-reboot').attr('data-folderlist', '1');
    $('#modal-shedule-reboot').modal('show');
  }
  setSheduleReboot() {
    $('#modal-shedule-reboot').modal('hide');
    var timestamp = +new Date($('#datetime-shedule-reboot').val());
    if( timestamp == null || isNaN(timestamp)) {
      $.toast({
        title: 'wrong shedule datetime',
        type: 'error',
        delay: 5000
      });
      return;
    }
    timestamp = timestamp / 1000;

    if( $('#datetime-shedule-reboot').attr("data-folderlist") == '1' ) {
      this.rebootServerList( timestamp );
    } else {
      var server_id = $('#datetime-shedule-reboot').attr("data-serverid");
      this.disableElement("#button-server-reboot-add");
      this.elementAddSpinner("#button-server-reboot-add", 'text-info');
      var request = this.backend.setSheduleReboot(server_id, timestamp);
      $.when(request).done(() => {
        this.enableElement("#button-server-reboot-add");
        this.elementRemoveSpinner("#button-server-reboot-add");
        this.showSingleServer( server_id );
      });

    }
  }
  deleteSheduleReboot() {
    var server_id = $( '#server-info-name' ).attr("data-serverid");
    this.disableElement("#button-server-reboot-add");
    this.elementAddSpinner("#button-server-reboot-add", 'text-info');
    var request = this.backend.deleteSheduleReboot(server_id);
    $.when(request).done(() => {
      this.enableElement("#button-server-reboot-add");
      this.elementRemoveSpinner("#button-server-reboot-add");
      this.showSingleServer( server_id );
    });
  }

  saveServerConfig() {
    this.disableElement("#button-save-server-config");
    this.elementAddSpinner("#button-save-server-config", 'text-info');

    var server_id = $( '#server-info-name' ).attr("data-serverid");
    var name = $('#server-config-name').val();
    var hostname = $('#server-config-hostname').val();
    var user_distribution = $('#server-config-distri').val();
    var ssh_private_key = $('#server-config-privatekey').val();
    var ssh_port = $('#server-config-sshport').val();
    var ssh_username = $('#server-config-sshusername').val();

    var request = this.backend.saveServerConfig( {server_id: server_id, name: name,
      hostname: hostname,
      user_distribution: user_distribution,
      ssh_private_key: ssh_private_key,
      ssh_port: ssh_port,
      ssh_username: ssh_username
    });

    $.when(request).done(() => {
      this.enableElement("#button-save-server-config");
      this.elementRemoveSpinner("#button-save-server-config");
      $('#modal-server-config').modal('hide');
      this.showSingleServer( server_id );
    });

  }
  editFolderConfig() {
    var folder_id = Number($( '#folder-info-name' ).attr("data-folderid"));
    var folder = this.backend.getFolder( folder_id );

    $('#folder-config-name').val(folder.name);
    $('#folder-IconPicker').val(folder.icon);
    $('#folder-IconPickerPreview').removeClass();
    $('#folder-IconPickerPreview').addClass(folder.icon);

    $('#folder-config-privatekey').val(folder.ssh_private_key);
    if( folder.ssh_port == 0 )
      $('#folder-config-sshport').val('');
    else
      $('#folder-config-sshport').val(folder.ssh_port);
    $('#folder-config-sshusername').val(folder.ssh_username);

    $('#modal-folder-config').modal('show');
  }
  saveFolderConfig() {
    var folder_id = $( '#folder-info-name' ).attr("data-folderid");
    var name = $('#folder-config-name').val();
    var icon = $('#folder-IconPicker').val();
    var ssh_private_key = $('#folder-config-privatekey').val();
    var ssh_port = $('#folder-config-sshport').val();
    var ssh_username = $('#folder-config-sshusername').val();

    this.backend.saveFolderConfig( {folder_id: folder_id, name: name, icon: icon,
      ssh_private_key: ssh_private_key,
      ssh_port: ssh_port,
      ssh_username: ssh_username
    });

    $('#modal-folder-config').modal('hide');
  }
  showGlobalConfig() {
    $('#global-ssh-private-key').val('');
    $('#global-ssh-port').val('');
    $('#global-ssh-username').val('');
    $('#global-distribution-command').val('');
    $('#global-distribution-version-command').val('');

    var config = this.backend.getGlobalConfig();

    $('#global-ssh-private-key').val(config.default_ssh_private_key);
    $('#global-ssh-port').val(config.default_ssh_port);
    $('#global-ssh-username').val(config.default_ssh_username);
    $('#global-distribution-command').val(config.default_distribution_command);
    $('#global-distribution-version-command').val(config.default_distribution_version_command);

    $('#modal-global-settings').modal('show');
  }
  distributionConfigFinished() {
    $('#distribution-config > tbody').empty();
    var distriConfig = this.backend.getDistributionConfig();
    $.each( distriConfig, function( key, value ) {
      var id = value.config_id;
      var match = value.distribution_match;

      var row = '<tr><td data-id="' + id + '">' + match + "</td>";
      row += '<td><button data-configid="' + id + '" type="button" class="btn btn-sm btn-primary btn-edit-distribution mr-2">Edit</button>';
      row += '<button data-configid="' + id + '" type="button" class="btn btn-sm btn-danger btn-delete-distribution">Delete</button></td>';
      $('#distribution-config > tbody').append( row );
    });
  }
  eolConfigFinished() {
    $('#eol-config > tbody').empty();
    var EOLConfig = this.backend.getEOLConfig();
    $.each( EOLConfig, function( key, value ) {
      var id = value.eol_id;
      var match = value.distribution_match;
      var eol = value.EOL;

      var row = '<tr><td data-id="' + id + '">' + match + "</td>";
      row += '<td>' + eol + "</td>";
      row += '<td><button data-eolid="' + id + '" type="button" class="btn btn-sm btn-primary btn-edit-eol mr-2">Edit</button>';
      row += '<button data-eolid="' + id + '" type="button" class="btn btn-sm btn-danger btn-delete-eol">Delete</button></td>';
      $('#eol-config > tbody').append( row );
    });
  }

  saveDistributionConfig() {
    var config_id = $('#distri-configname').attr('data-configid');
    var config_name = $('#distri-configname').val();
    var distri_name = $('#distri-distriname').val();
    var distri_version = $('#distri-version').val();
    var uptime = $('#distri-uptime').val();
    var restart = $('#distri-restart').val();
    var update_list = $('#distri-update-list').val();
    var package_info = $('#distri-package-info').val();
    var package_changelog = $('#distri-package-changelog').val();
    var system_update = $('#distri-update-system').val();
    var package_update = $('#distri-update-package').val();
    var shedule_reboot_add = $('#distri-shedule-reboot-add').val();
    var shedule_reboot_get = $('#distri-shedule-reboot-get').val();
    var shedule_reboot_del = $('#distri-shedule-reboot-del').val();

    if( config_id >= 0 ) {
      this.backend.updateDistributionConfig({config_id: config_id,
        config_name: config_name,
        distri_name: distri_name,
        distri_version: distri_version,
        uptime: uptime,
        restart: restart,
        update_list: update_list,
        package_info: package_info,
        package_changelog: package_changelog,
        system_update: system_update,
        package_update: package_update,
        shedule_reboot_add: shedule_reboot_add,
        shedule_reboot_get: shedule_reboot_get,
        shedule_reboot_del: shedule_reboot_del
      } );
    } else {
      this.backend.insertDistributionConfig({config_name: config_name,
        distri_name: distri_name,
        distri_version: distri_version,
        uptime: uptime,
        restart: restart,
        update_list: update_list,
        package_info: package_info,
        package_changelog: package_changelog,
        system_update: system_update,
        package_update: package_update,
        shedule_reboot_add: shedule_reboot_add,
        shedule_reboot_get: shedule_reboot_get,
        shedule_reboot_del: shedule_reboot_del
      });
    }
    $('#modal-distribution-config').modal('hide');
  }
  saveEOLConfig() {
    var eol_id = $('#eol-distri-configname').attr('data-eolid');
    var distri_name = $('#eol-distri-configname').val();
    var eol = $('#distri-eol').val();

    if( eol_id >= 0 ) {
      this.backend.updateEOLConfig({eol_id: eol_id,
        distri_name: distri_name,
        eol: eol,
      });
    } else {
      this.backend.insertEOLConfig({distri_name: distri_name,
        eol: eol,
      });
    }
    $('#modal-eol-config').modal('hide');
  }
  deleteDistributionConfig(config_id) {
    this.backend.deleteDistributionConfig(config_id);
  }
  deleteEOLConfig( eol_id ) {
    this.backend.deleteEOLConfig( eol_id );
  }

  showDistributionConfig(config_id) {
    $('#distri-configname').attr('data-configid', config_id);
    $('#distri-configname').val('');
    $('#distri-distriname').val('');
    $('#distri-version').val('');
    $('#distri-uptime').val('');
    $('#distri-restart').val('');
    $('#distri-update-list').val('');
    $('#distri-package-info').val('');
    $('#distri-package-changelog').val('');
    $('#distri-update-system').val('');
    $('#distri-update-package').val('');
    $('#distri-shedule-reboot-add').val('');
    $('#distri-shedule-reboot-get').val('');
    $('#distri-shedule-reboot-del').val('');

    var config = this.backend.getDistributionSingleConfig(config_id);

    $('#distri-configname').val(config.distribution_match);
    $('#distri-distriname').val(config.distribution_command);
    $('#distri-version').val(config.distribution_version_command);
    $('#distri-uptime').val(config.uptime_command);
    $('#distri-restart').val(config.restart_command);
    $('#distri-update-list').val(config.updates_list_command);
    $('#distri-package-info').val(config.update_info_command);
    $('#distri-package-changelog').val(config.update_changelog_command);
    $('#distri-update-system').val(config.update_system_command);
    $('#distri-update-package').val(config.update_package_command);
    $('#distri-shedule-reboot-add').val(config.reboot_set_command);
    $('#distri-shedule-reboot-get').val(config.reboot_get_command);
    $('#distri-shedule-reboot-del').val(config.reboot_del_command);

    $('#modal-distribution-config').modal('show');
  }

  showEOLConfigEdit(eol_id) {
    $('#eol-distri-configname').attr('data-eolid', eol_id);
    $('#eol-distri-configname').val('');
    $('#distri-eol').val('');

    var eol = this.backend.getEOLSingleConfig(eol_id);

    $('#eol-distri-configname').val(eol.distribution_match);
    $('#distri-eol').val(eol.EOL);

    $('#modal-eol-config').modal('show');
  }

  editImportantUpdate(server_id, iu_id) {
    $("#modal-impartant-update-comment-text").attr("data-iu_id", iu_id);
    $("#modal-impartant-update-comment-text").attr("data-server_id", server_id);

    var row = $('#important-updates > tbody > tr > td[data-iu_id="' + iu_id + '"]').parent();
    var pack = $(row).find(".package-name").text();
    var comment = $(row).find(".comment").text();

    $("#modal-impartant-package-name").val(pack);
    $("#modal-impartant-update-comment-text").val(comment);
    $('#modal-impartant-update-comment').modal('show');
  }
  deleteImportantUpdate(server_id, iu_id) {
    var request = this.backend.deleteImportantUpdate(server_id, iu_id);
    $.when(request).done(() => {
      this.showSingleServer( server_id );
    });
  }
  showAddImportantUpdate(server_id) {
    $("#modal-impartant-update-comment-text").attr("data-iu_id", '-1');
    $("#modal-impartant-update-comment-text").attr("data-server_id", server_id);
    $("#modal-impartant-package-name").val('');
    $("#modal-impartant-update-comment-text").val('');
    $('#modal-impartant-update-comment').modal('show');
  }
  showPackageChangelog(update_id, element_id) {
    $('#modal-full-text-Text').val('');
    this.disableElement('#'+element_id);
    this.elementAddSpinner('#'+element_id, 'text-light');
    var request = this.backend.requestPackageChangelog(update_id);
    $.when(request).done(() => {
      this.enableElement('#'+element_id);
      this.elementRemoveSpinner('#'+element_id);
    });
  }
  showPackageChangelogReturn(changelog) {
    $('#modal-full-text-Text').val( changelog );
    $('#modal-full-textTitle').text('Package changelog');
    $('#modal-full-text').modal('show');
  }
  showPackageInfo(update_id, element_id) {
    $('#modal-full-text-Text').val('');
    this.disableElement('#'+element_id);
    this.elementAddSpinner('#'+element_id, 'text-light');
    var request = this.backend.requestPackageInfo(update_id);
    $.when(request).done(() => {
      this.enableElement('#'+element_id);
      this.elementRemoveSpinner('#'+element_id);
    });
  }
  showPackageInfoReturn(info) {
    $('#modal-full-text-Text').val( info );
    $('#modal-full-textTitle').text('Package info');
    $('#modal-full-text').modal('show');
  }
  updatePackage(server_id, update_id, element_id) {
    this.disableElement('#'+element_id);
    this.elementAddSpinner('#'+element_id, 'text-light');
    var request = this.backend.updatePackage(server_id, update_id);
    $.when(request).done(() => {
      this.enableElement('#'+element_id);
      this.elementRemoveSpinner('#'+element_id);
      this.showSingleServer(server_id);
    });
  }
  updateMarkImportant(server_id, update_id) {
    $("#modal-impartant-update-comment-text").attr("data-iu_id", '-1');
    $("#modal-impartant-update-comment-text").attr("data-server_id", server_id);
    var row = $('#server-updates > tbody > tr > td[data-update_id="' + update_id + '"]').parent();
    var pack = $(row).find(".package-name").text();
    $("#modal-impartant-package-name").val(pack);
    $("#modal-impartant-update-comment-text").val('');
    $('#modal-impartant-update-comment').modal('show');
  }
  // kann weg
  //updateUnmarkImportant(server_id, iu_id) {
  //  this.backend.deleteImportantUpdate(server_id, iu_id);
  //}
  saveImportantUpdate() {
    var iu_id = $("#modal-impartant-update-comment-text").attr("data-iu_id");
    var server_id = $("#modal-impartant-update-comment-text").attr("data-server_id");
    var pack = $("#modal-impartant-package-name").val();
    var comment = $("#modal-impartant-update-comment-text").val();
    var request;

    this.disableElement("#button-save-important-update-comment");
    this.elementAddSpinner("#button-save-important-update-comment", 'text-info');

    if( iu_id == -1 ) {
      request = this.backend.addImportantUpdate(server_id, pack, comment);
    } else {
      request = this.backend.editImportantUpdate(server_id, iu_id, pack, comment);
    }
    $.when(request).done(() => {
      this.enableElement("#button-save-important-update-comment");
      this.elementRemoveSpinner("#button-save-important-update-comment");
      this.showSingleServer( server_id );
      $('#modal-impartant-update-comment').modal('hide');
    });
  }

  updateServer() {
    this.timerStart();
    this.disableElement("#button-update-server");
    this.elementAddSpinner("#button-update-server", 'text-info');
    var server_id = $( '#server-info-name' ).attr("data-serverid");
    var request = this.backend.updateServer(server_id);
    $.when(request).done(() => {
      this.enableElement("#button-update-server");
      this.elementRemoveSpinner("#button-update-server");
      this.showSingleServer( server_id );
      this.timerEnd("host update finished in");
    });
  }

  scrollSeverListOutput() {
    $('#folder-server-list > tbody > tr > td[class="output-full"] > textarea').each( function(key, value) {
      value.scrollTop = value.scrollHeight;
    }); 
  }
  setServerListColumns() {
    var showUpdatePackages = $('#switch-show-update-packages').is(':checked');
    var showFullOutput = $('#switch-show-full-output').is(':checked');
    if( showFullOutput ) {
      $('#folder-server-list > tbody > tr > td.sys').hide();
      $('#folder-server-list > thead > tr > th.sys').hide();
      $('#folder-server-list > tbody > tr > td.eol').hide();
      $('#folder-server-list > thead > tr > th.eol').hide();
      $('#folder-server-list > tbody > tr > td.rr').hide();
      $('#folder-server-list > thead > tr > th.rr').hide();
      $('#folder-server-list > tbody > tr > td.sr').hide();
      $('#folder-server-list > thead > tr > th.sr').hide();
      $('#folder-server-list > tbody > tr > td.updates').show();
      $('#folder-server-list > thead > tr > th.updates').show();
      $('#folder-server-list > tbody > tr > td.update_list').hide();
      $('#folder-server-list > thead > tr > th.update_list').hide();
      $('#folder-server-list > tbody > tr > td.output').hide();
      $('#folder-server-list > thead > tr > th.output').hide();
      $('#folder-server-list > tbody > tr > td.output-full').show();
      $('#folder-server-list > thead > tr > th.output-full').show();
    } else {
      $('#folder-server-list > tbody > tr > td.sys').show();
      $('#folder-server-list > thead > tr > th.sys').show();
      $('#folder-server-list > tbody > tr > td.eol').show();
      $('#folder-server-list > thead > tr > th.eol').show();
      $('#folder-server-list > tbody > tr > td.rr').show();
      $('#folder-server-list > thead > tr > th.rr').show();
      $('#folder-server-list > tbody > tr > td.sr').show();
      $('#folder-server-list > thead > tr > th.sr').show();
      $('#folder-server-list > tbody > tr > td.output').show();
      $('#folder-server-list > thead > tr > th.output').show();
      $('#folder-server-list > tbody > tr > td.output-full').hide();
      $('#folder-server-list > thead > tr > th.output-full').hide();
      if( showUpdatePackages ) {
        $('#folder-server-list > tbody > tr > td.updates').hide();
        $('#folder-server-list > thead > tr > th.updates').hide();

        $('#folder-server-list > tbody > tr > td.eol').hide();
        $('#folder-server-list > thead > tr > th.eol').hide();
        $('#folder-server-list > tbody > tr > td.rr').hide();
        $('#folder-server-list > thead > tr > th.rr').hide();
        $('#folder-server-list > tbody > tr > td.sr').hide();
        $('#folder-server-list > thead > tr > th.sr').hide();

        $('#folder-server-list > tbody > tr > td.update_list').show();
        $('#folder-server-list > thead > tr > th.update_list').show();
      } else {
        $('#folder-server-list > tbody > tr > td.update_list').hide();
        $('#folder-server-list > thead > tr > th.update_list').hide();

        $('#folder-server-list > tbody > tr > td.updates').show();
        $('#folder-server-list > thead > tr > th.updates').show();

        $('#folder-server-list > tbody > tr > td.eol').show();
        $('#folder-server-list > thead > tr > th.eol').show();
        $('#folder-server-list > tbody > tr > td.rr').show();
        $('#folder-server-list > thead > tr > th.rr').show();
        $('#folder-server-list > tbody > tr > td.sr').show();
        $('#folder-server-list > thead > tr > th.sr').show();
      }
    }
  }

  searchFolder() {
    var input, filter, txtValue;
    input = $('#input-searchfolder')[0];
    filter = input.value.toUpperCase();

    $( 'a.folder-item').each( function(key, value) {
      txtValue = value.textContent || value.innerText;
      if (txtValue.toUpperCase().indexOf(filter) > -1) {
        $(value).show();
      } else {
        $(value).hide();
      }
    });
  }

  searchServerList() {
    var input, filter, txtValue;
    input = $('#input-searchserver-list')[0];
    filter = input.value.toUpperCase();

    $('#folder-server-list > tbody > tr').each( function(key, value) {
      txtValue = $(value).find(".server-name").text();
      if (txtValue.toUpperCase().indexOf(filter) > -1) {
        $(value).show();
      } else {
        $(value).hide();
      }
    });
  }
  testSelectedServer() {
    var anyChecked = false;
    var anyUnchecked = false;
    $('#folder-server-list > tbody > tr > td[class="server-select"] > div > input').each( function(key, value) {
      if( $(value).prop('checked') )
        anyChecked = true;
      else
        anyUnchecked = true;
    });

    if( anyChecked == false ) {
      $('#button-inventory-all-server').prop('disabled', true);

      $('#button-update-all-server').prop('disabled', true);
    } else {
      $('#button-inventory-all-server').text('Inventory Selected');
      $('#button-inventory-all-server').prop('disabled', false);

      $('#button-update-all-server').text('Update Selected');
      $('#button-update-all-server').prop('disabled', false);
    }
    if( anyUnchecked == false ) {
      $('#button-inventory-all-server').text('Inventory All');

      $('#button-update-all-server').text('Update All');
    }
  }
  serverListShowOutput(output) {
    $('#modal-full-textTitle').text('Update output');
    $('#modal-full-text-Text').val(output);
    $('#modal-full-text').modal('show');
    // why setting val take so much time???
    setTimeout(function(){ var textarea = $('#modal-full-text-Text'); textarea.scrollTop(textarea[0].scrollHeight - textarea.height()); }, 400);
  }
  
  serverListSelect() {
   $('#folder-server-list > tbody > tr:visible').each( function(key, value) {
      $(value).find('.server-select > div > input').prop('checked', true);
    });
  }

  serverListUnselect() {
   $('#folder-server-list > tbody > tr:visible').each( function(key, value) {
      $(value).find('.server-select > div > input').prop('checked', false);
    });
  }

  inventoryServerList() {
    this.timerStart();
    var server_for_inventory = Array();

    this.disableElement('#button-inventory-all-server');
    this.elementAddSpinner('#button-inventory-all-server');
    this.disableElement('#button-all-server-reboot-add');
    this.disableElement('#button-all-server-reboot-del');
    this.disableElement('#button-update-all-server');

    $('#folder-server-list > tbody').each( (key, value) => {
      $(value).find('.inventory').empty();
      $(value).find('.inventory').removeClass('table-success table-danger');
      $(value).find('.output').empty();
      $(value).find('.output-full').empty();
    });

    $('#folder-server-list > tbody > tr:visible').each( function(key, value) {
      if( $(value).find('.server-select > div > input').prop('checked') ) {
        var server_id = $(value).find(".index").attr("data-server_id");
        server_for_inventory.push( server_id );
      }
    });

    this.batchrunCount = server_for_inventory.length;
    this.batchrunFinished = 0;
    this.batchrunError = 0;

    this.setCommandProgressError(0, "");
    this.setCommandProgress(0, this.batchrunFinished + " / " + this.batchrunCount);

    $.each( server_for_inventory, ( key, value ) => {
      this.inventoryServerListShowWaiting( value );
      this.backend.inventoryServerFromServerlist( value );
    });
  }

  inventoryServerListShowWaiting( server_id ) {
    var row = $('#folder-server-list > tbody > tr > td[data-server_id="' + server_id + '"]').parent();
    var cmd = $(row).find(".inventory");

    var spinner  = '<div class="spinner-border spinner-border-sm text-info" role="status">';
        spinner += '</div>';
    $(cmd).html( spinner );
  }

  inventoryServerListReturn( server_id, success, message ) {
    this.batchrunFinished++;

    if( this.batchrunFinished >= this.batchrunCount ) {
      $('#button-all-server-reboot-add').prop('disabled', false);
      $('#button-all-server-reboot-del').prop('disabled', false);
      $('#button-inventory-all-server').prop('disabled', false);
      $('#button-update-all-server').prop('disabled', false);

      this.enableElement('#button-inventory-all-server');
      this.elementRemoveSpinner('#button-inventory-all-server');
      this.enableElement('#button-all-server-reboot-add');
      this.enableElement('#button-all-server-reboot-del');
      this.enableElement('#button-update-all-server');

      this.timerEnd("host list inventory finished in");
    }

    var server = this.backend.getServer( server_id );
    this.folderServerListSetServer(server);
    var row = this.folderServerListGetServerRow( server_id );
    var colInv = $(row).find(".inventory");
    if( success ) {
      $(colInv).addClass("table-success");
      $(colInv).text("OK");
    } else {
      this.batchrunError++;
      var progress = Math.ceil(100 / this.batchrunCount * this.batchrunError);
      this.setCommandProgressError(progress, this.batchrunError + " Errors");
      
      $(colInv).addClass("table-danger");
      $(colInv).text("ERROR");

      this.folderServerListSetOutput(server_id, message);
    }
    var progress = Math.ceil(100 / this.batchrunCount * (this.batchrunFinished - this.batchrunError));
    this.setCommandProgress(progress, this.batchrunFinished + " / " + this.batchrunCount);
  }

  setCommandProgress(value, text) {
    $('#command-progress').attr('aria-valuenow', value).css('width', value + "%");
    $('#command-progress').html('<span>' + text + '</span>');
  }
  setCommandProgressError(value, text) {
    $('#command-progressError').attr('aria-valuenow', value).css('width', value + "%");
    $('#command-progressError').html('<span>' + text + '</span>');
  }
  folderServerListSetOutput(server_id, output) {
    var row = this.folderServerListGetServerRow( server_id );
    var colOut = $(row).find(".output");
    var colOutF = $(row).find(".output-full");

    var outputfulldiv = $('<textarea rows="10" class="form-control" style="width: 600px; white-space: pre; overflow: auto;" readonly />');
    outputfulldiv.text( output );
    $(colOutF).html( outputfulldiv );
    setTimeout(function(){ outputfulldiv.scrollTop(outputfulldiv[0].scrollHeight - outputfulldiv.height()); }, 400, outputfulldiv);

    var button = $('<button/>', {
      text: 'Show',
      class: 'btn btn-info btn-sm update-show-output'
    });
    $(button).attr('data-output', output);
    $(colOut).html(button);
  }
  
  updateServerList() {
    this.timerStart();
    var server_for_update = Array();

    $('#folder-server-list > tbody').each( function(key, value) {
      $(value).find('.update').empty();
      $(value).find('.update').removeClass('table-success table-danger');
      $(value).find('.output').empty();
      $(value).find('.output-full').empty();
    });

    $('#folder-server-list > tbody > tr:visible').each( function(key, value) {
      if( $(value).find('.server-select > div > input').prop('checked') ) {
        // onyl update if updates available
        if( parseInt($(value).find(".updates").text()) > 0 ) {
          var server_id = $(value).find(".index").attr("data-server_id");
          server_for_update.push( server_id );
        }
      }
    });

    if( server_for_update.length > 0 ) {
      this.disableElement('#button-inventory-all-server');
      this.disableElement('#button-all-server-reboot-add');
      this.disableElement('#button-all-server-reboot-del');
      this.disableElement('#button-update-all-server');
      this.elementAddSpinner('#button-update-all-server');
    }
    this.batchrunCount = server_for_update.length;
    this.batchrunFinished = 0;
    this.batchrunError = 0;

    this.setCommandProgressError(0, "");
    this.setCommandProgress(0, this.batchrunFinished + " / " + this.batchrunCount);

    $.each( server_for_update, ( key, value ) => {
      this.updateServerListShowWaiting( value );
      this.backend.updateServerFromServerlist( value );
    });
  }

  scrollUpdateOutput() {
    var outputfulldiv = $('<textarea rows="10" class="form-control" style="width: 600px; white-space: pre; overflow: auto;" readonly />');
    outputfulldiv.text( output );
    $(colOutF).html( outputfulldiv );
    setTimeout(function(){ outputfulldiv.scrollTop(outputfulldiv[0].scrollHeight - outputfulldiv.height()); }, 800);

  }

  updateServerListShowWaiting( server_id ) {
    var row = $('#folder-server-list > tbody > tr > td[data-server_id="' + server_id + '"]').parent();
    var cmd = $(row).find(".update");

    var spinner  = '<div class="spinner-border spinner-border-sm text-info" role="status">';
        spinner += '</div>';
    $(cmd).html( spinner );
  }

  updateServerListReturn( server_id, success, message ) {
    this.batchrunFinished++;

    if( this.batchrunFinished >= this.batchrunCount ) {
      $('#button-all-server-reboot-add').prop('disabled', false);
      $('#button-all-server-reboot-del').prop('disabled', false);
      $('#button-inventory-all-server').prop('disabled', false);
      $('#button-update-all-server').prop('disabled', false);

      this.enableElement('#button-inventory-all-server');
      this.elementRemoveSpinner('#button-update-all-server');
      this.enableElement('#button-all-server-reboot-add');
      this.enableElement('#button-all-server-reboot-del');
      this.enableElement('#button-update-all-server');

      this.timerEnd("host list update finished in");
    }

    var server = this.backend.getServer( server_id );
    this.folderServerListSetServer(server);
    var row = this.folderServerListGetServerRow( server_id );
    var colPat = $(row).find(".update");
    if( success ) {
      $(colPat).addClass("table-success");
      $(colPat).text("OK");
    } else {
      this.batchrunError++;
      var progress = Math.ceil(100 / this.batchrunCount * this.batchrunError);
      this.setCommandProgressError(progress, this.batchrunError + " Errors");

      $(colPat).addClass("table-danger");
      $(colPat).text("ERROR");
    }
    this.folderServerListSetOutput(server_id, message);
    var progress = Math.ceil(100 / this.batchrunCount * (this.batchrunFinished - this.batchrunError));
    this.setCommandProgress(progress, this.batchrunFinished + " / " + this.batchrunCount);
  }

  rebootServerList( rebootTimestamp ) {
    var server_for_reboot = Array();

    this.disableElement('#button-inventory-all-server');
    this.disableElement('#button-all-server-reboot-add');
    this.disableElement('#button-all-server-reboot-del');
    this.disableElement('#button-update-all-server');
    this.elementAddSpinner('#button-all-server-reboot-add');

    $('#folder-server-list > tbody > tr:visible').each( function(key, value) {
      if( $(value).find('.server-select > div > input').prop('checked') &&
        $(value).find('.sr').text() == '-'  // only server without sheduled reboot
      ) {
        var server_id = $(value).find(".index").attr("data-server_id");
        server_for_reboot.push( server_id );
      }
    });

    this.batchrunCount = server_for_reboot.length;
    this.batchrunFinished = 0;
    this.batchrunError = 0;

    this.setCommandProgressError(0, "");
    this.setCommandProgress(0, this.batchrunFinished + " / " + this.batchrunCount);

    $.each( server_for_reboot, ( key, value ) => {
      this.rebootServerListShowWaiting( value );
      this.backend.rebootServerFromServerlist( value, rebootTimestamp );
    });
  }

  rebootServerListShowWaiting( server_id ) {
    var row = $('#folder-server-list > tbody > tr > td[data-server_id="' + server_id + '"]').parent();
    var cmd = $(row).find(".sr");

    var spinner  = '<div class="spinner-border spinner-border-sm text-info" role="status">';
        spinner += '</div>';
    $(cmd).html( spinner );
  }

  rebootServerListReturn(server_id, success) {
    this.batchrunFinished++;
    if( this.batchrunFinished >= this.batchrunCount ) {
      $('#button-all-server-reboot-add').prop('disabled', false);
      $('#button-all-server-reboot-del').prop('disabled', false);
      $('#button-inventory-all-server').prop('disabled', false);
      $('#button-update-all-server').prop('disabled', false);

      this.enableElement('#button-inventory-all-server');
      this.elementRemoveSpinner('#button-all-server-reboot-add');
      this.enableElement('#button-all-server-reboot-add');
      this.enableElement('#button-all-server-reboot-del');
      this.enableElement('#button-update-all-server');
    }

    var server = this.backend.getServer( server_id );
    this.folderServerListSetServer(server);
    var row = this.folderServerListGetServerRow( server_id );
    var colSR = $(row).find(".sr");
    if( success ) {
      $(colSR).addClass("table-success");
    } else {
      this.batchrunError++;
      var progress = Math.ceil(100 / this.batchrunCount * this.batchrunError);
      this.setCommandProgressError(progress, this.batchrunError + " Errors");
      $(colSR).addClass("table-danger");
    }
    var progress = Math.ceil(100 / this.batchrunCount * (this.batchrunFinished - this.batchrunError));
    this.setCommandProgress(progress, this.batchrunFinished + " / " + this.batchrunCount);
  }
  rebootDelServerList() {
    var server_for_reboot = Array();

    this.disableElement('#button-inventory-all-server');
    this.disableElement('#button-all-server-reboot-add');
    this.disableElement('#button-all-server-reboot-del');
    this.disableElement('#button-update-all-server');
    this.elementAddSpinner('#button-all-server-reboot-add');

    $('#folder-server-list > tbody > tr:visible').each( function(key, value) {
      if( $(value).find('.server-select > div > input').prop('checked') &&
        $(value).find('.sr').text() != '-'  // only server with sheduled reboot
      ) {
        var server_id = $(value).find(".index").attr("data-server_id");
        server_for_reboot.push( server_id );
      }
    });

    this.batchrunCount = server_for_reboot.length;
    this.batchrunFinished = 0;
    this.batchrunError = 0;

    this.setCommandProgressError(0, "");
    this.setCommandProgress(0, this.batchrunFinished + " / " + this.batchrunCount);

    $.each( server_for_reboot, ( key, value ) => {
      this.rebootServerListShowWaiting( value );
      this.backend.rebootDelServerFromServerlist( value );
    });
  }

  rebootDelServerListReturn(server_id, success) {
    this.batchrunFinished++;
    if( this.batchrunFinished >= this.batchrunCount ) {
      $('#button-all-server-reboot-add').prop('disabled', false);
      $('#button-all-server-reboot-del').prop('disabled', false);
      $('#button-inventory-all-server').prop('disabled', false);
      $('#button-update-all-server').prop('disabled', false);

      this.enableElement('#button-inventory-all-server');
      this.elementRemoveSpinner('#button-all-server-reboot-add');
      this.enableElement('#button-all-server-reboot-add');
      this.enableElement('#button-all-server-reboot-del');
      this.enableElement('#button-update-all-server');
    }
    var server = this.backend.getServer( server_id );
    this.folderServerListSetServer(server);
    var row = this.folderServerListGetServerRow( server_id );
    var colSR = $(row).find(".sr");
    if( success ) {
      $(colSR).addClass("table-success");
    } else {
      this.batchrunError++;
      var progress = Math.ceil(100 / this.batchrunCount * this.batchrunError);
      this.setCommandProgressError(progress, this.batchrunError + " Errors");

      $(colSR).addClass("table-danger");
    }
    var progress = Math.ceil(100 / this.batchrunCount * (this.batchrunFinished - this.batchrunError));
    this.setCommandProgress(progress, this.batchrunFinished + " / " + this.batchrunCount);
  }
}
