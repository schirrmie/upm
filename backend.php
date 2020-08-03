<?php
  header('Content-Type: application/json');

  require_once 'config.php';
  require_once 'folder.php';
  require_once 'update.php';
  require_once 'server.php';
  if( !isset( $_POST["fct_name"] ) ) {
    echo json_encode( array('success' => false, 'error' => "no fct_name set") );
    return false;
  }
  if( !isset( $_POST["fct_data"] ) ) {
    echo json_encode( array('success' => false, 'error' => "no fct_data set") );
    return false;
  }

  $fct_name = $_POST["fct_name"];
  $fct_data = $_POST["fct_data"];
  
  switch( $fct_name ) {
    case "get_folders":
      if( folder::get($folders) )
        echo json_encode( array('success' => true, 'folders' => $folders) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "get_servers":
      if( server::get($servers) )
        echo json_encode( array('success' => true, 'servers' => $servers) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "add_server":
      $server = $fct_data["server"];
      if( server::add($server, $server) >= 0 )
 			  echo json_encode( array('success' => true) );
      else
        echo json_encode( array('success' => false, 'message' => "Error while adding host: ") );
			break;
		case "mass_import":
      $import_data = $fct_data["import_data"];
      
      if( folder::massImport($import_data) )
        echo json_encode( array('success' => true, 'message' => "Finished mass import") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while Importing: ") );
      break;
    case "add_folder":
      $folder = $fct_data["folder"];
      
			if(	folder::add($folder) > 0 ) {
				echo json_encode( array('success' => true) );
			} else {
				echo json_encode( array('success' => false, 'message' => "Can't open database!") );
			}
			break;
    case "delete_server":
      $server_id = $fct_data["server_id"];

      if( server::delete( $server_id ) )
        echo json_encode( array('success' => true, 'message' => "Successfully delete host")  );
      else
        echo json_encode( array('success' => false, 'message' => "Error while deleting host") );
      break;
    case "move_server" :
      $server_id = $fct_data["server_id"];
      $folder_id = $fct_data["folder_id"];

      if( server::move($server_id, $folder_id) ) {
        echo json_encode( array('success' => true, 'message' => "Successfully moved host")  );
      } else {
        echo json_encode( array('success' => false, 'message' => "Error while moving host") );
      }
      break;
    case "delete_folder":
      $folder_id = $fct_data["folder_id"];
      if( folder::delete( $folder_id ) )
        echo json_encode( array('success' => true, 'message' => "Successfully deleted folder.")  );
      else
        echo json_encode( array('success' => false, 'message' => "error while deleting folder!") );
      break;
    case "move_folder":
      $folder_id = $fct_data["folder_id"];
      $parent_id = $fct_data["parent_id"];

      if( folder::move($folder_id, $parent_id) )
        echo json_encode( array('success' => true, 'message' => "Successfully moved folder")  );
      else
        echo json_encode( array('success' => false, 'message' => "Error while moving folder: ") );
      break;
    case "get_server_info":
      $server_id = $fct_data["server_id"];
      //$initial_run = $fct_data["initial_run"];
      //$update_root_folder = $fct_data["update_root_folder"];
      if( server::getInfo($server_id, $server) )
        echo json_encode( array('success' => true, "server_id" => $server_id, 'server' => $server) );
      else
        echo json_encode( array('success' => false, "server_id" => $server_id));
      break;
    case "get_folder_info":
      $folder_id = $fct_data["folder_id"];

      if( folder::getInfo($folder_id, $folder) )
        echo json_encode( array('success' => true, "folder_id" => $folder_id, 'folder' => $folder) );
      else
        echo json_encode( array('success' => false, "folder_id" => $folder_id) );
      break;
    case "update_server_config":
      $server_id = $fct_data["server_id"];
      $name = $fct_data["name"];
      $hostname = $fct_data["hostname"];
      $ssh_private_key = $fct_data["ssh_private_key"];
      $ssh_port = $fct_data["ssh_port"];
      $ssh_username = $fct_data["ssh_username"];
      $user_distribution = $fct_data["user_distribution"];

      if( configuration::setServerConfig($server_id, $name, $hostname, $user_distribution,
        $ssh_private_key, $ssh_port, $ssh_username, $server) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'server' => $server, 'message' => 'Successfully updated host config'));
      else
        echo json_encode( array('success' => false, 'message' => "error while updating host config!") );
      break;

    case "update_folder_config":
      $folder_id = $fct_data["folder_id"];
      $name = $fct_data["name"];
      $icon = $fct_data["icon"];
      $ssh_private_key = $fct_data["ssh_private_key"];
      $ssh_port = $fct_data["ssh_port"];
      $ssh_username = $fct_data["ssh_username"];

      if( configuration::setFolderConfig($folder_id, $name, $icon,
        $ssh_private_key, $ssh_port, $ssh_username) )
        echo json_encode( array('success' => true, 'folder_id' => $folder_id, 'message' => 'Successfully updated folder config'));
      else
        echo json_encode( array('success' => false, 'message' => "Error while updating folder config!") );
      break;
      //ToDo
    case "get_global_config":
      if( configuration::getGlobalConfig( $config ) )
        echo json_encode( array('success' => true, 'config' => $config) );
      else
        echo json_encode( array('success' => false, 'message' => "Can't get global config") );
      break;
    case "get_distribution_config_1":
      $config_id = $fct_data["config_id"];

      if( configuration::getDistributionConfig($config_id, $config) )
        echo json_encode( array('success' => true, 'config' => $config) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "get_distribution_config":
      if( configuration::getDistributionOverview($configs) )
        echo json_encode( array('success' => true, 'config' => $configs) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "get_eol_config":
      if( configuration::getEolOverview($configs) )
        echo json_encode( array('success' => true, 'config' => $configs) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "get_eol_config_1":
      $eol_id = $fct_data["eol_id"];
      if( configuration::getEolConfig($eol_id, $config) )
        echo json_encode( array('success' => true, 'config' => $config) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "server_get_update_output":
      $server_update_output_id = $fct_data["server_update_output_id"];
      if( update::getServerUpdateOutput($server_update_output_id, $server_id, $output) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'output' => $output) );
      else
        echo json_encode( array('success' => false) );
      break;
    case "add_package_important":
      $server_id = $fct_data['server_id'];
      $package = $fct_data['pack'];
      $comment = $fct_data['comment'];

      if( update::addImportantUpdate($server_id, $package, $comment, $server) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'server' => $server ) );
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id ) );
      break;
    case "edit_package_important":
      $iu_id = $fct_data['iu_id'];
      $server_id = $fct_data['server_id'];
      $package = $fct_data['pack'];
      $comment = $fct_data['comment'];

      if( update::updateImportantUpdate($server_id, $iu_id, $package, $comment, $server) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'server' => $server ) );
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id ) );
      break;
    case "delete_package_important":
      $iu_id = $fct_data['iu_id'];
      $server_id = $fct_data['server_id'];

      if( update::deleteImportantUpdate($server_id, $iu_id, $server) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'server' => $server ) );
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id ) );
      break;
    case "insert_eol_config":
      $distri_name = $fct_data["distri_name"];
      $eol = $fct_data["eol"];

      if( configuration::addEolConfig($distri_name, $eol) )
        echo json_encode( array('success' => true, 'message' => "Successfully added eol config") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while adding eol config") );
      break;
    case "update_eol_config":
      $eol_id = $fct_data["eol_id"];
      $distri_name = $fct_data["distri_name"];
      $eol = $fct_data["eol"];

      if( configuration::updateEolConfig($eol_id, $distri_name, $eol) )
        echo json_encode( array('success' => true, 'message' => "Successfully updated eol config") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while updating eol config") );
      break;
    case "delete_eol_config":
      $eol_id = $fct_data["eol_id"];

      if( configuration::deleteEolConfig($eol_id) )
        echo json_encode( array('success' => true, 'message' => "Successfully deleted eol config") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while deleting eol config") );
      break;
    case "insert_distribution_config":
      $config_name = $fct_data["config_name"];
      $distri_name = $fct_data["distri_name"];
      $distri_version = $fct_data["distri_version"];
      $uptime = $fct_data["uptime"];
      $restart = $fct_data["restart"];
      $update_list = $fct_data["update_list"];
      $package_info = $fct_data["package_info"];
      $package_changelog = $fct_data["package_changelog"];
      $system_update = $fct_data["system_update"];
      $package_update = $fct_data["package_update"];
      $shedule_reboot_add = $fct_data["shedule_reboot_add"];
      $shedule_reboot_get = $fct_data["shedule_reboot_get"];
      $shedule_reboot_del = $fct_data["shedule_reboot_del"];

      if( configuration::addDistributionConfig($config_name, $distri_name, $distri_version,
        $uptime, $restart, $update_list, $package_info, $package_changelog,
        $system_update, $package_update, $shedule_reboot_add, $shedule_reboot_get, $shedule_reboot_del) )
        echo json_encode( array('success' => true, 'message' => "Successfully added distribution config") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while adding distribution config") );
      break;
    case "update_distribution_config":
      $config_id = $fct_data["config_id"];
      $config_name = $fct_data["config_name"];
      $distri_name = $fct_data["distri_name"];
      $distri_version = $fct_data["distri_version"];
      $uptime = $fct_data["uptime"];
      $restart = $fct_data["restart"];
      $update_list = $fct_data["update_list"];
      $package_info = $fct_data["package_info"];
      $package_changelog = $fct_data["package_changelog"];
      $system_update = $fct_data["system_update"];
      $package_update = $fct_data["package_update"];
      $shedule_reboot_add = $fct_data["shedule_reboot_add"];
      $shedule_reboot_get = $fct_data["shedule_reboot_get"];
      $shedule_reboot_del = $fct_data["shedule_reboot_del"];

      if( configuration::updateDistributionConfig($config_id, $config_name, $distri_name, $distri_version,
        $uptime, $restart, $update_list, $package_info, $package_changelog,
        $system_update, $package_update, $shedule_reboot_add, $shedule_reboot_get, $shedule_reboot_del) )
        echo json_encode( array('success' => true, 'message' => "Successfully updated distribution config") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while updating distribution config") );
      break;
    case "delete_distribution_config":
      $config_id = $fct_data["config_id"];

      if( configuration::deleteDistributionConfig($config_id) )
        echo json_encode( array('success' => true, 'message' => "Successfully deleted distribution config") );
      else
        echo json_encode( array('success' => false, 'message' => "Error while deleting distribution config") );
      break;
    case "update_global_config":
      $default_ssh_private_key = $fct_data["default_ssh_private_key"];
      $default_ssh_port = $fct_data["default_ssh_port"];
      $default_ssh_username = $fct_data["default_ssh_username"];
      $default_distribution_command = $fct_data["default_distribution_command"];
      $default_distribution_version_command = $fct_data["default_distribution_version_command"];

      if( configuration::updateGlobalConfig($default_ssh_private_key, 
        $default_ssh_port, $default_ssh_username, $default_distribution_command, $default_distribution_version_command) )
        echo json_encode( array('success' => true, 'message' => 'Successfully updated global config'));
      else
        echo json_encode( array('success' => false, 'message' => "Error while updating global config") );
      break;
    case "inventory_server":
    case "inventory_server_from_list":
      $server_id = $fct_data["server_id"];

      if( server::inventoryServer($server_id, $server, $error) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'server' => $server));
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id, 'message' => $error) );
      break;
    case "get_package_changelog":
      $update_id = $fct_data["update_id"];

      if( update::getPackageChangelog($update_id, $changelog) )
        echo json_encode( array('success' => true, 'changelog' => $changelog) );
      else
        echo json_encode( array('success' => false, 'message' => "Can't get package changelog"));
      break;
    case "get_package_info":
      $update_id = $fct_data["update_id"];
      
      if( update::getPackageInfo($update_id, $info) )
        echo json_encode( array('success' => true, 'info' => $info) );
      else
        echo json_encode( array('success' => false, 'message' => "Can't get package info"));
      break;
    case "update_package":
      $update_id = $fct_data["update_id"];
      $server_id = $fct_data["server_id"];
      if( update::updatePackage($update_id, $output, $server) )
        echo json_encode( array('success' => true, 'server_output' => $output, 'server_id' => $server_id, 'server' => $server) );
      else
        echo json_encode( array('success' => false, 'message' => "Error updating package"));
      break;
    case "update_server":
    case "update_server_from_list":
      $server_id = $fct_data['server_id'];

      if( update::updateServer($server_id, $output, $server) )
        echo json_encode( array('success' => true, 'server_output' => $output, 'server_id' => $server_id, 'server' => $server) );
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id, 'message' => "Error while update host") );
      break;
    case "shedule_reboot_add":
    case "shedule_reboot_add_list":
      $server_id = $fct_data['server_id'];
      $timestamp = $fct_data['timestamp'];

      if( server::addSheduleReboot($server_id, $timestamp, $sheduled_restart, $server) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'reboot' => $sheduled_restart, 'server' => $server) );
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id, 'message' => "Error while set shedule reboot") );
      break;
    case "shedule_reboot_del":
    case "shedule_reboot_del_list":
      $server_id = $fct_data['server_id'];

      if( server::delSheduleReboot($server_id, $server) )
        echo json_encode( array('success' => true, 'server_id' => $server_id, 'server' => $server) );
      else
        echo json_encode( array('success' => false, 'server_id' => $server_id, 'message' => "Error while delete shedule reboot") );
      break;
    default:
      echo json_encode( array('success' => false, 'error' => "unknown fct_name " . $fct_name) );
      return false;
      break;
  }

  return true;