<?php

  require_once 'phpseclib/Net/SSH2.php';
  require_once 'phpseclib/File/ANSI.php';
  require_once 'phpseclib/Math/BigInteger.php';
  require_once 'phpseclib/Crypt/Base.php';
  require_once 'phpseclib/Crypt/Rijndael.php';
  require_once 'phpseclib/Crypt/AES.php';
  require_once 'phpseclib/Crypt/Blowfish.php';
  require_once 'phpseclib/Crypt/DES.php';
  require_once 'phpseclib/Crypt/Hash.php';
  require_once 'phpseclib/Crypt/Random.php';
  require_once 'phpseclib/Crypt/RC2.php';
  require_once 'phpseclib/Crypt/RC4.php';
  require_once 'phpseclib/Crypt/TripleDES.php';
  require_once 'phpseclib/Crypt/Twofish.php';
  require_once 'phpseclib/Crypt/RSA.php';

  require_once 'meekrodb.2.3.class.php';

  abstract class CommandNames {
    const Distribution = "distribution_command";
    const DistributionVersion = "distribution_version_command";
    const Uptime = "uptime_command";
    const RestartRequired = "restart_command";
    const ListUpdates = "updates_list_command";
    const PatchInfo = "update_info_command";
    const PatchChangelog = "update_changelog_command";
    const UpdateSystem = "update_system_command";
    const UpdatePackage = "update_package_command";
    const RebootSet = "reboot_set_command";
    const RebootGet = "reboot_get_command";
    const RebootDel = "reboot_del_command";
  }

  class UPM {

    private static $ssh;

    public static function init() {
      $config = include('config.php');
      DB::$user = $config['database']['user'];
      DB::$password = $config['database']['pass'];
      DB::$dbName = $config['database']['name'];
      DB::$host = $config['database']['host'];
      DB::$encoding = 'utf8';
      DB::$error_handler = false;
      DB::$throw_exception_on_error = true;
      DB::$throw_exception_on_nonsql_error = true;

      UPM::$ssh = null;
      
      // session for saving config between ajax calls
      session_start();
      session_write_close();
      if( !isset($_SESSION['default_ssh_private_key']) ) {
        UPM::loadGlobalConfig();
      }
      if( !isset($_SESSION['distribution_config']) ) {
        UPM::loadDistributionConfig();
      }
      if( !isset($_SESSION['eol_config']) ) {
        UPM::loadEolConfig();
      }
    }

    public static function getFolders(&$folders) {
      try {
        $results = DB::query("
        SELECT folder_parent_id, F.name AS parent, folder_child_id, F2.name AS child,
        (SELECT COUNT(*) FROM upm_folder_folder AS B WHERE A.folder_child_id = B.folder_parent_id) AS has_childs 
        FROM upm_folder_folder AS A INNER JOIN upm_folder AS F ON A.folder_parent_id = F.folder_id
        INNER JOIN upm_folder AS F2 ON A.folder_child_id = F2.folder_id
        UNION ALL
        SELECT '0' as folder_parent_id, 'root' as parent, F.folder_id, F.name as child,
        (SELECT COUNT(*) FROM upm_folder_folder AS B WHERE F.folder_id = B.folder_parent_id) AS has_childs 
        FROM upm_folder AS F WHERE F.folder_id NOT IN (SELECT folder_child_id FROM upm_folder_folder) ORDER BY parent, child;
        ");
        $folders = array();
        foreach($results as $row) {
          array_push($folders, $row);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getServers(&$servers) {
      try {
        $results = DB::query("SELECT S.server_id FROM upm_server AS S;");
        $servers = array();
        foreach($results as $row) {
          array_push($servers, $row);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addServer($servername, $hostname, $folder_id = null) {
      try {
        DB::insert('upm_server', array( 'name' => $servername, 'hostname' => $hostname) );

        $server_id = DB::insertId();
        if ( $folder_id != null ) {
          if( !UPM::moveServer($server_id, $folder_id) )
            return -1;
        }
        return $server_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
  	}
	  public static function moveServer($server_id, $folder_id) {
      try {
        DB::delete('upm_folder_server', 'server_id=%d', $server_id);

        if( $folder_id > 0 ) {
          DB::insert('upm_folder_server', array( 'folder_id' => $folder_id, 'server_id' => $server_id) );
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
	  }
    public static function addFolder($foldername, $parent_id = null) {
      try {
        DB::insert('upm_folder', array( 'name' => $foldername) );
        $folder_id = DB::insertId();
        if ( $parent_id != null) {
          UPM::moveFolder($folder_id, $parent_id);
        }
        return $folder_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
    }
 	
    public static function moveFolder($folder_id, $parent_id)
    {
      if( $folder_id == 0 ) {
        return false;
      }
      try {
        DB::delete('upm_folder_folder', "folder_child_id=%d", $folder_id);
        if( $parent_id != 0 )
          DB::insert('upm_folder_folder', array( 'folder_parent_id' => $parent_id, 'folder_child_id' => $folder_id) );
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }

    private static function getFirstFolderByName($foldername) {
      try {
        $folder_id = DB::queryFirstField("SELECT folder_id FROM upm_folder WHERE name=%s", $foldername);
        if( $folder_id == null )
          return -1;
        return $folder_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
    }
    public static function massImport( $importdata ) {
      $j = 0;
      foreach(preg_split("/((\r?\n)|(\r\n?))/", $importdata) as $string) {
        $data[$j] = $string;
        $j++;
      }

      $dataindex = 0;
      if( !UPM::recursiveImport(0, $data, $dataindex, 0) )
        return false;
      return true;
    }

    private static function recursiveImport($ParentID, $data, &$dataindex, $WhiteSpaceCount) {
      $OldWhiteSpaceCount = $WhiteSpaceCount;
      $NewParentID = $ParentID;

      for( ; $dataindex < sizeof($data); $dataindex++ ) {
        $string = $data[$dataindex];

        if( $string == "" )
          return true;
        $len = strlen($string);
        $string = ltrim($string);
        $len2 = strlen($string);

        $WhiteSpaceCount = $len - $len2;
      
        if( $WhiteSpaceCount > $OldWhiteSpaceCount ) {
          if( !UPM::recursiveImport($NewParentID, $data, $dataindex, $WhiteSpaceCount) ) {
            return false;
          }
          $dataindex--;
          continue;
        } else if ( $WhiteSpaceCount < $OldWhiteSpaceCount ) {
          return true;
        }

        $name = substr($string, 1);
        $char = $string[0];
        switch($char) {
          case '+':
            $ServerID = UPM::addServer($name, $name, $ParentID);
            if( $ServerID == -1 ) {
              return false;
            }
            break;
          case '#':
            $NewParentID = UPM::getFirstFolderByName( $name );
            if( $NewParentID > 0 )
              break;
            $NewParentID = UPM::addFolder($name, $ParentID);
            if( $NewParentID == -1 ) {
              return false;
            }
            break;
          default:
            error_log("wrong format string: " . $string . " char: " . $char);
            return false;
        }
      }
      return true;
    }
  
    private static function deleteAllImportantUpdates($server_id) {
      try {
        DB::delete('upm_server_important_updates', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function deleteAllUpdates($server_id) {
      try {
        DB::delete('upm_server_updates', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function deleteServerFromAnyFolder($server_id) {
      try {
        DB::delete('upm_folder_server', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    // ToDo delete server output and more?
    public static function deleteServer($server_id) {
      if( !UPM::deleteAllImportantUpdates($server_id) )
        return false;
      if( !UPM::deleteAllUpdates($server_id) )
        return false;
      if( !UPM::deleteServerFromAnyFolder( $server_id) )
        return false;

      try {
        DB::delete('upm_server', "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }

    private static function deleteFolderFromFolderServer($folder_id) {
      try {
        DB::delete('upm_folder_server', "folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function deleteFolderFromFolderFolder($folder_id) {
      try {
        DB::delete('upm_folder_folder', "folder_parent_id=%d OR folder_child_id=%d", $folder_id, $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteFolder($folder_id) {
      if( $folder_id == 0 ) {
        error_log("Can't delete Root folder!");
        return false;
      }
      if( !UPM::deleteFolderFromFolderServer( $folder_id) )
        return false;
      if( !UPM::deleteFolderFromFolderFolder( $folder_id) )
        return false;

      try {
        DB::delete('upm_folder', "folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }

    }
    public static function getServerInfo($server_id, &$serverinfo) {
      try {
        $serverinfo = DB::queryFirstRow('SELECT S.*, FS.folder_id FROM upm_server AS S LEFT OUTER JOIN upm_folder_server AS FS ON FS.server_id = S.server_id WHERE S.server_id=%d', $server_id);
        $results = DB::query("SELECT U.update_id, U.server_id, U.package, IU.important_update_id, IU.comment 
          FROM upm_server_updates AS U LEFT OUTER JOIN upm_server_important_updates AS IU 
          ON U.server_id = IU.server_id AND U.package = IU.package 
          WHERE U.server_id=%d ORDER BY U.package", $server_id);
        $updates = array();
        foreach( $results as $row ) {
          array_push($updates, $row);
        }
        $serverinfo['update_list'] = $updates;
        $serverinfo['updates'] = sizeof($updates);

        $results = DB::query("SELECT IU.important_update_id, IU.package, IU.comment
          FROM upm_server_important_updates AS IU
          WHERE IU.server_id=%d ORDER BY IU.package", $server_id);
        $imp_updates = array();
        foreach( $results as $row ) {
          array_push($imp_updates, $row);
        }
        $serverinfo['imp_updates'] = $imp_updates;

        $results = DB::query("SELECT server_update_output_id, update_date
          FROM upm_server_update_output WHERE server_id=%d ORDER BY update_date DESC", $server_id);
        $update_outputs = array();
        foreach( $results as $row ) {
          array_push($update_outputs, $row);
        }
        $serverinfo['update_outputs'] = $update_outputs;
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getFolderInfo($folder_id, &$folderinfo) {
      try {
        $folderinfo = DB::queryFirstRow("SELECT * FROM upm_folder WHERE folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function setServerConfig($server_id, $name, $hostname, $user_distribution,
      $ssh_private_key, $ssh_port, $ssh_username, &$serverinfo) {

      if( $ssh_port == "" )
        $ssh_port = 0;

      try {
        DB::update("upm_server", array(
          'name' => $name,
          'hostname' => $hostname,
          'user_distribution' => $user_distribution,
          'ssh_private_key' => $ssh_private_key,
          'ssh_port' => $ssh_port,
          'ssh_username' => $ssh_username), "server_id=%d", $server_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function setFolderConfig($folder_id, $name, $icon,
      $ssh_private_key, $ssh_port, $ssh_username) {

      if( $ssh_port == "" )
        $ssh_port = 0;

      try {
        DB::update("upm_folder", array(
          'name' => $name,
          'icon' => $icon,
          'ssh_private_key' => $ssh_private_key,
          'ssh_port' => $ssh_port,
          'ssh_username' => $ssh_username), "folder_id=%d", $folder_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getDistributionConfig($config_id, &$config) {
      try {
        $config = DB::queryFirstRow("SELECT * FROM upm_distri_config WHERE config_id=%d", $config_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getDistributionOverview(&$configs) {
      try {
        $results = DB::query("SELECT config_id, distribution_match FROM upm_distri_config");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addDistributionConfig($config_name, $distri_name, $distri_version,
      $uptime, $restart, $update_list, $package_info, $package_changelog,
      $system_update, $package_update, $shedule_reboot_add, $shedule_reboot_get, $shedule_reboot_del) {

      try {
        DB::insert('upm_distri_config',
          array( 'distribution_match' => $config_name, 'distribution_command' => $distri_name,
          'distribution_version_command' => $distri_version, 'uptime_command' => $uptime,
          'restart_command' => $restart, 'updates_list_command' => $update_list, 'update_info_command' => $package_info,
          'update_changelog_command' => $package_changelog, 'update_system_command' => $system_update,
          'update_package_command' => $package_update, 'reboot_set_command' => $shedule_reboot_add,
          'reboot_get_command' => $shedule_reboot_get, 'reboot_del_command' => $shedule_reboot_del) );
        UPM::loadDistributionConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateDistributionConfig($config_id, $config_name, $distri_name, $distri_version,
      $uptime, $restart, $update_list, $package_info, $package_changelog,
      $system_update, $package_update, $shedule_reboot_add, $shedule_reboot_get, $shedule_reboot_del) {

      try {
        DB::update('upm_distri_config',
          array( 'distribution_match' => $config_name, 'distribution_command' => $distri_name,
          'distribution_version_command' => $distri_version, 'uptime_command' => $uptime,
          'restart_command' => $restart, 'updates_list_command' => $update_list, 'update_info_command' => $package_info,
          'update_changelog_command' => $package_changelog, 'update_system_command' => $system_update,
          'update_package_command' => $package_update, 'reboot_set_command' => $shedule_reboot_add,
          'reboot_get_command' => $shedule_reboot_get, 'reboot_del_command' => $shedule_reboot_del), "config_id=%d", $config_id);
        UPM::loadDistributionConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteDistributionConfig($config_id) {
      try {
        DB::delete('upm_distri_config', "config_id=%d", $config_id);
        UPM::loadDistributionConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function loadDistributionConfig() {
      try {
        $results = DB::query("SELECT * FROM upm_distri_config");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        session_start();
        $_SESSION['distribution_config'] = $configs;
        session_write_close();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getEolOverview(&$configs) {
      try {
        $results = DB::query("SELECT * FROM upm_eol_config ORDER BY distribution_match");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getEolConfig($eol_id, &$eol) {
      try {
        $eol = DB::queryFirstRow("SELECT * FROM upm_eol_config WHERE eol_id=%d", $eol_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addEolConfig($distri_name, $eol) {
      try {
        DB::insert('upm_eol_config',
          array( 'distribution_match' => $distri_name, 'EOL' => $eol) );
        UPM::loadEolConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateEolConfig($eol_id, $distri_name, $eol) {
      try {
        DB::update("upm_eol_config", array(
          'distribution_match' => $distri_name,
          'EOL' => $eol), "eol_id=%d", $eol_id);
        UPM::loadEolConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function deleteEolConfig($eol_id) {
      try {
        DB::delete('upm_eol_config', "eol_id=%d", $eol_id);
        UPM::loadEolConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function loadEolConfig() {
      try {
        $results = DB::query("SELECT * FROM upm_eol_config");
        $configs = array();
        foreach( $results as $row ) {
          array_push($configs, $row);
        }
        session_start();
        $_SESSION['eol_config'] = $configs;
        session_write_close();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }

    public static function getServerUpdateOutput($server_update_output_id, &$server_id, &$output) {
      try {
        $row = DB::queryFirstRow("SELECT server_id, output FROM upm_server_update_output WHERE server_update_output_id=%d", $server_update_output_id);
        $server_id = $row['server_id'];
        $output = $row['output'];
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addImportantUpdate($server_id, $package_name, $comment, &$serverinfo) {
      try {
        DB::insert('upm_server_important_updates',
          array( 'server_id' => $server_id, 'package' => $package_name, 'comment' => $comment) );
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateImportantUpdate($server_id, $iu_id, $package_name, $comment, &$serverinfo) {
      try {
        DB::update("upm_server_important_updates", array(
          'package' => $package_name,
          'comment' => $comment), "important_update_id=%d", $iu_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }

    }
    public static function deleteImportantUpdate($server_id, $iu_id, &$serverinfo) {
      try {
        DB::delete('upm_server_important_updates', "important_update_id=%d", $iu_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateGlobalConfig($default_ssh_private_key, 
      $default_ssh_port, $default_ssh_username, $default_distribution_command, $default_distribution_version_command) {
      try {
        $count = DB::queryFirstField("SELECT COUNT(*) AS C FROM upm_global_config");
        if( $count == 0 ) {
          DB::insert('upm_global_config',
            array( 'default_distribution_command' => $default_distribution_command,
            'default_distribution_version_command' => $default_distribution_version_command,
            'default_ssh_port' => $default_ssh_port, 'default_ssh_username' => $default_ssh_username,
            'default_ssh_private_key' => $default_ssh_private_key ) );
        } else {
          DB::update('upm_global_config',
            array( 'default_distribution_command' => $default_distribution_command,
            'default_distribution_version_command' => $default_distribution_version_command,
            'default_ssh_port' => $default_ssh_port, 'default_ssh_username' => $default_ssh_username,
            'default_ssh_private_key' => $default_ssh_private_key), '1=1' );
        }
        UPM::loadGlobalConfig();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }

    }
    private static function loadGlobalConfig() {
      try {
        $config = DB::queryFirstRow("SELECT * FROM upm_global_config");
        session_start();
        $_SESSION['default_ssh_private_key'] = $config['default_ssh_private_key'];
        $_SESSION['default_ssh_public_key'] = $config['default_ssh_public_key'];
        $_SESSION['default_ssh_port'] = $config['default_ssh_port'];
        $_SESSION['default_ssh_username'] = $config['default_ssh_username'];
        $_SESSION['default_distribution_command'] = $config['default_distribution_command'];
        $_SESSION['default_distribution_version_command'] = $config['default_distribution_version_command'];
        session_write_close();
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function sshRunCommand($ssh_hostname, $ssh_port, $ssh_username, $ssh_key,
      $command, &$command_ret, &$error) {

     try { 
      $originalConnectionTimeout = ini_get('default_socket_timeout');
      ini_set('default_socket_timeout', 2);

      if( UPM::$ssh != null ) {
		    $command_ret = trim(UPM::$ssh->exec( $command ));
        $command_exit_code = UPM::$ssh->getExitStatus();

        //error_log( "hostname: $ssh_hostname reuse - $command_ret" );
        if( $command_exit_code != 0 ) {
          $error = "Error! executing command: $command return: $command_ret";
          return false;
        } else {
          return true;
        }
      }

      UPM::$ssh = new phpseclib\Net\SSH2( $ssh_hostname, $ssh_port );

      ini_set('default_socket_timeout', $originalConnectionTimeout);

      if( !UPM::$ssh ) {
        $error = "Error while connecting to host [$ssh_hostname].\n" . error_get_last()['message'];
        return false;
      }

      $key = new phpseclib\Crypt\RSA();
      $key->loadKey( $ssh_key );

      if( !$key ) {
        $error = "Error while loading private key!\n" . error_get_last()['message'];
        return false;
      }
      $login = UPM::$ssh->login($ssh_username, $key);
      if( !$login ) {
        $error = "Error while loging in, with user [" . $ssh_username . "]. Check username or ssh private key!\n" . error_get_last()['message'];
        return false;
      }
      UPM::$ssh->setTimeout(0);
      $command_ret = trim(UPM::$ssh->exec( $command ));
      $command_exit_code = UPM::$ssh->getExitStatus();
      if( $command_exit_code != 0 ) {
        $error = "Error! executing command: $command return: $command_ret";
        return false;
      }
      //error_log( "hostname: $ssh_hostname new - $command_ret" );

     } catch (Exception $e) {
       $error = $e->getMessage();
       return false;
     }
      return true;
    }
    private static function serverDetectDistribution($server_id, &$distri, &$distri_version, &$error) {
      if( !UPM::serverRunCommand($server_id, $_SESSION['default_distribution_command'], $command_ret, $error) ) {
        return false;
      }
      $distri = $command_ret;
      if( !UPM::serverRunCommand($server_id, $_SESSION['default_distribution_version_command'], $command_ret, $error) )
        return false;
      $distri_version = $command_ret;
      if( !UPM::serverInsertDistribution($server_id, $distri, $distri_version) ) {
        $error = "Can't update server info with distribution + version!";
        return false;
      }
      return true;
    }
    private static function serverInsertDistribution($server_id, $distri, $distri_version) {
      try {
        DB::update('upm_server',
          array( 'distribution' => $distri, 'distribution_version' => $distri_version), "server_id=%d", $server_id );
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function serverRunCommandName($server_id, $commandname, &$command_ret, &$error) {
      if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
        if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
          $error = "Can't detect distribution: " . $error2;
          return false;
        }
      }
      if( !UPM::getDistributionCommand($distri, $distri_version, $commandname, $cmd) ) {
        $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
        return false;
      }
      return UPM::serverRunCommand($server_id, $cmd, $command_ret, $error);
    }
    public static function serverRunCommand($server_id, $command, &$command_ret, &$error) {
      if( !UPM::getServerInfo($server_id, $server) ) {
        $error = "Can't receive server!";
        return false;
      }
      $folder_id = UPM::getFolderIdFromServer( $server_id );
      $folder = null;
      if( $folder_id >= 0 ) {
        if( !UPM::getFolderInfo($folder_id, $folder) ) {
          $error = "Can't receive folder!";
          return false;
        }
      }

      $ssh_port = 0;
      $ssh_key = "";
      $ssh_username = "";
      $ssh_hostname = $server['hostname'];

      if( $server['ssh_port'] > 0 )
        $ssh_port = $server['ssh_port'];
      else if( $folder != null && $folder['ssh_port'] > 0 )
        $ssh_port = $folder['ssh_port'];
      else
        $ssh_port = $_SESSION['default_ssh_port'];

      if( $server['ssh_private_key'] != "" )
        $ssh_key = $server['ssh_private_key'];
      else if( $folder != null && $folder['ssh_private_key'] != "" )
        $ssh_key = $folder['ssh_private_key'];
      else
        $ssh_key = $_SESSION['default_ssh_private_key'];

      if( $server['ssh_username'] != "" )
        $ssh_username = $server['ssh_username'];
      else if( $folder != null && $folder['ssh_username'] != "" )
        $ssh_username = $folder['ssh_username'];
      else
        $ssh_username = $_SESSION['default_ssh_username'];

      $ret =  UPM::sshRunCommand($ssh_hostname, $ssh_port, $ssh_username, $ssh_key,
      $command, $command_ret, $error); 

      return $ret;
    }
    private static function getFolderIdFromServer( $server_id ) {
      try {
        $folder_id = DB::queryFirstField("SELECT folder_id FROM upm_folder_server WHERE server_id=%d", $server_id);
        if( $folder_id == null )
          return -1;
        return $folder_id;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return -1;
      }
    }
    private static function getServerDistribution($server_id, &$distri, &$distri_version) {
      if( !UPM::getServerInfo($server_id, $server) )
        return false;

      $distribution_config = array();

      $distri = "";
      $distri_version = "";
      if( $server['user_distribution'] != "" ) {
        $distri = $server['user_distribution'];
      } else {
        if( $server['distribution'] == null && $server['distribution_version'] == null ) {
          return false;
        } else {
          $distri = $server['distribution'];
          $distri_version = $server['distribution_version'];
        }
      }
      return true;
    }
    private static function getDistributionCommand($distri, $distri_version, $commandname, &$cmd) {
      foreach( $_SESSION['distribution_config'] as &$value) {
        if( $value['distribution_match'] == $distri . " " . $distri_version ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['distribution_config']  as &$value) {
        if( $value['distribution_match'] == $distri ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['distribution_config']  as &$value) {
        if( fnmatch($value['distribution_match'], $distri . " " . $distri_version) ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['distribution_config']  as &$value) {
        if( fnmatch($value['distribution_match'], $distri) ) {
          $cmd = $value[$commandname];
          return true;
        }
      }
      unset($value);

      return false;
    }
    private static function serverClearUpdates($server_id) {
      try {
        DB::delete('upm_server_updates', 'server_id=%d', $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function serverDeleteOldUpdates($server_id, $update_list) {
      try {
        $results = DB::query("SELECT * FROM upm_server_updates WHERE server_id=%d", $server_id);
        $db_updates = array();
        foreach( $results as $row ) {
          array_push($db_updates, $row);
        }
        foreach($db_updates as &$db_update) {
          $is_present = false;
          foreach($update_list as &$update) {
            if( $db_update['package'] == $update ) {
              $is_present = true;
              break;
            }
          }
          if( $is_present == false ) {
            if( !UPM::deleteServerUpdate($db_update['update_id']) )
              return false;
          }
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function deleteServerUpdate($update_id) {
      try {
        DB::delete('upm_server_updates', 'update_id=%d', $update_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function addServerUpdate($server_id, $package) {
      try {
        $count = DB::queryFirstField("SELECT COUNT(*) FROM upm_server_updates WHERE server_id=%d AND package=%s", $server_id, $package);
        if( $count > 0 )
          return true;

        DB::insert('upm_server_updates', array( 'server_id' => $server_id, 'package' => $package) );
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    private static function serverGetEOL($server_id, &$EOL) {
      if( ! UPM::getServerDistribution($server_id, $distri, $distri_version) )
        return false;
      return UPM::getEOLByDistribution($distri, $distri_version, $EOL);
    }
    //ToDo better than $distri . " " . $distri_version see getDistributionCommand
    private static function getEOLByDistribution($distri, $distri_version, &$EOL) {
      if( $distri_version != "" )
        $distri = $distri . " " . $distri_version;

      foreach( $_SESSION['eol_config']  as &$value) {
        if( $value['distribution_match'] == $distri ) {
          $EOL = $value['EOL'];
          return true;
        }
      }
      unset($value);

      foreach( $_SESSION['eol_config']  as &$value) {
        if( fnmatch($value['distribution_match'], $distri) ) {
          $EOL = $value['EOL'];
          return true;
        }
      }
      unset($value);

      return false;
    }
    private static function updateServerInfo($server_id, $uptime, $restart_required, 
      $distribution, $distribution_version, $EOL, $sheduled_restart, $inventory_time) {
      try {
        DB::update("upm_server", array(
          'uptime' => $uptime,
          'restart_required' => $restart_required,
          'distribution' => $distribution,
          'distribution_version' => $distribution_version,
          'EOL' => $EOL,
          'sheduled_restart' => $sheduled_restart,
          'last_inventoried' => $inventory_time), "server_id=%d", $server_id);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }

    }
    public static function inventoryServer($server_id, &$serverinfo, &$error) {
      $inventory_time = date("Y-m-d H:i:s");
      if( !UPM::serverRunCommandName($server_id, CommandNames::Uptime, $uptime, $error) ) {
        return false;
      }
      if( !UPM::serverRunCommandName($server_id, CommandNames::RestartRequired, $restart_required, $error) ) {
        return false;
      }
      if( !UPM::serverRunCommandName($server_id, CommandNames::RebootGet, $sheduled_restart, $error) ) {
        return false;
      }
      if( !UPM::serverRunCommandName($server_id, CommandNames::Distribution, $distribution, $error) ) {
        return false;
      }
      if( !UPM::serverRunCommandName($server_id, CommandNames::DistributionVersion, $distribution_version, $error) ) {
        return false;
      }
      if( !UPM::serverRunCommandName($server_id, CommandNames::ListUpdates, $update_list_str, $error) ) {
        return false;
      }

      if( $update_list_str == "" ) {
        if( !UPM::serverClearUpdates($server_id) ) {
          $error = "Error while clearing server updates";
          return false;
        }
      } else {
        $update_list = array_map('trim', explode("\n", $update_list_str));
        foreach( $update_list as &$value) {
          if( ! UPM::addServerUpdate($server_id, $value) ) {
            $error = "Error while adding server update to table!";
            return false;
          }
        }
        if( !UPM::serverDeleteOldUpdates($server_id, $update_list) ) {
          $error = "Error while deleting old server updates in table!";
          return false;
        }
        unset($value);
      }

      if( ! UPM::serverGetEOL($server_id, $EOL) ) {
        $EOL = null;
      }
      if( $sheduled_restart > 0 )
        $sheduled_restart = date("Y-m-d H:i:s", $sheduled_restart);
      else
        $sheduled_restart = null;

      if( ! UPM::updateServerInfo($server_id, $uptime, $restart_required,
        $distribution, $distribution_version, $EOL, $sheduled_restart, $inventory_time) ) {
        $error = "Error while updating host info in table";
        return false;
      }
      if( !UPM::getServerInfo($server_id, $serverinfo)) {
        $error = "Error while getting host info from table!";
        return false;
      }
      return true;
    }
    public static function getPackageChangelog($update_id, &$changelog) {
      try {
        $changelog = DB::queryFirstField("SELECT changelog FROM upm_server_updates WHERE update_id=%d", $update_id);
        if( $changelog == null ) {
          $server_id = DB::queryFirstField("SELECT server_id FROM upm_server_updates WHERE update_id=%d", $update_id);
          $package = DB::queryFirstField("SELECT package FROM upm_server_updates WHERE update_id=%d", $update_id);
          if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
            if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
              $error = "Can't detect distribution: " . $error2;
              return false;
            }
          }
          if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::PatchChangelog, $cmd) ) {
            $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
            return false;
          }
          $cmdReplaced = str_replace('${PackageName}', $package, $cmd);
          if( !UPM::serverRunCommand($server_id, $cmdReplaced, $changelog, $error) ) {
            return false;
          }
          DB::update("upm_server_updates", array('changelog' => $changelog), "update_id=%d", $update_id);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getPackageInfo($update_id, &$info) {
      try {
        $info = DB::queryFirstField("SELECT info FROM upm_server_updates WHERE update_id=%d", $update_id);
        if( $info == null ) {
          $server_id = DB::queryFirstField("SELECT server_id FROM upm_server_updates WHERE update_id=%d", $update_id);
          $package = DB::queryFirstField("SELECT package FROM upm_server_updates WHERE update_id=%d", $update_id);
          if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
            if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
              $error = "Can't detect distribution: " . $error2;
              return false;
            }
          }
          if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::PatchInfo, $cmd) ) {
            $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
            return false;
          }
          $cmdReplaced = str_replace('${PackageName}', $package, $cmd);
          if( !UPM::serverRunCommand($server_id, $cmdReplaced, $info, $error) ) {
            return false;
          }
          DB::update("upm_server_updates", array('info' => $info), "update_id=%d", $update_id);
        }
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updatePackage($update_id, &$output, &$serverinfo) {
      try {
        $server_id = DB::queryFirstField("SELECT server_id FROM upm_server_updates WHERE update_id=%d", $update_id);
        $package = DB::queryFirstField("SELECT package FROM upm_server_updates WHERE update_id=%d", $update_id);
        if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::UpdatePackage, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        $cmdReplaced = str_replace('${PackageName}', $package, $cmd);
        if( !UPM::serverRunCommand($server_id, $cmdReplaced, $output, $error) ) {
          return false;
        }
  
        if( !UPM::serverRunCommandName($server_id, CommandNames::ListUpdates, $update_list_str, $error) ) {
          return false;
        }

        if( $update_list_str == "" ) {
          if( !UPM::serverClearUpdates($server_id) ) {
            $error = "Error while clearing server updates";
            return false;
          }
        } else {
          $update_list = array_map('trim', explode("\n", $update_list_str));
          foreach( $update_list as &$value) {
            if( ! UPM::addServerUpdate($server_id, $value) ) {
              $error = "Error while adding server update to table!";
              return false;
            }
          }
          if( !UPM::serverDeleteOldUpdates($server_id, $update_list) ) {
            $error = "Error while deleting old server updates in table!";
            return false;
          }
          unset($value);
        }

        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function updateServer($server_id, &$output, &$serverinfo) {
      try {
        $update_time = date("Y-m-d H:i:s");
        if( !UPM::serverRunCommandName($server_id, CommandNames::UpdateSystem, $output, $error) ) {
          return false;
        }
        if( !UPM::serverClearUpdates($server_id) ) {
          $error = "Error while clearing server updates";
          return false;
        }
        DB::update("upm_server", array('last_updated' => $update_time), "server_id=%d", $server_id);
        DB::insert('upm_server_update_output', array( 'server_id' => $server_id, 'update_date' => $update_time, 'output' => $output) );
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function addSheduleReboot($server_id, $timestamp, &$sheduled_restart, &$serverinfo) {
      try {
        if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::RebootSet, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        $dt = new DateTime();
        $dt->setTimestamp($timestamp);
        $YYYY = $dt->format('Y');
        $MM =   $dt->format('m');
        $DD =   $dt->format('d');
        $hh =   $dt->format('H');
        $mm =   $dt->format('i');

        $cmd = str_replace('${YYYY}', $YYYY, $cmd);
        $cmd = str_replace('${MM}', $MM, $cmd);
        $cmd = str_replace('${DD}', $DD, $cmd);
        $cmd = str_replace('${hh}', $hh, $cmd);
        $cmd = str_replace('${mm}', $mm, $cmd);
        if( !UPM::serverRunCommand($server_id, $cmd, $output, $error) ) {
          return false;
        }
        if( !UPM::serverRunCommandName($server_id, CommandNames::RebootGet, $sheduled_restart, $error) ) {
          return false;
        }
        if( $sheduled_restart > 0 )
          $sheduled_restart = date("Y-m-d H:i:s", $sheduled_restart);
        else
          $sheduled_restart = null;

        DB::update("upm_server", array('sheduled_restart' => $sheduled_restart), "server_id=%d", $server_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function delSheduleReboot($server_id, &$serverinfo) {
      try {
        if( !UPM::getServerDistribution($server_id, $distri, $distri_version) ) {
          if( !UPM::serverDetectDistribution($server_id, $distri, $distri_version, $error2) ) {
            $error = "Can't detect distribution: " . $error2;
            return false;
          }
        }
        if( !UPM::getDistributionCommand($distri, $distri_version, CommandNames::RebootSet, $cmd) ) {
          $error = "No command for $commandname specified for distribution " . $distri . " " . $distri_version;
          return false;
        }
        if( !UPM::serverRunCommandName($server_id, CommandNames::RebootDel, $output, $error) ) {
          return false;
        }

        DB::update("upm_server", array('sheduled_restart' => null), "server_id=%d", $server_id);
        UPM::getServerInfo($server_id, $serverinfo);
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
    public static function getGlobalConfig(&$config) {
      try {
        $config = DB::queryFirstRow('SELECT * FROM upm_global_config');
        return true;
      } catch(MeekroDBException $e) {
        error_log( "DB error " . $e->getMessage() );
        error_log( $e->getQuery() );
        return false;
      }
    }
  }
  UPM::init();
?>
